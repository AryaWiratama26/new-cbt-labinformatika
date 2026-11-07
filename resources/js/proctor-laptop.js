/**
 * Proctor Laptop - Face detection using MediaPipe
 * This module is dynamically imported from attempt.blade.php
 */

export class ProctorLaptop {
    constructor(config) {
        this.sessionId = config.sessionId;
        this.eventUrl = config.eventUrl;
        this.csrfToken = config.csrfToken;
        
        this.stream = null;
        this.videoEl = null;
        this.landmarker = null;
        this.detecting = false;
        this.lastEvents = {};
        this.pendingEvents = {};
        this.retryAt = {};
        this.heartbeatTimer = null;
    }

    async init() {
        try {
            if (!window.isSecureContext || !navigator.mediaDevices) {
                throw new Error('Secure camera context is required');
            }

            this.stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            
            this.videoEl = document.createElement('video');
            this.videoEl.autoplay = true;
            this.videoEl.playsInline = true;
            this.videoEl.muted = true;
            this.videoEl.srcObject = this.stream;
            this.videoEl.style.display = 'none';
            document.body.appendChild(this.videoEl);

            if (!await this.initLandmarker()) {
                throw new Error('Face landmarker failed to initialize');
            }
            this.startDetection();

            return true;
        } catch (e) {
            console.error('Camera proctoring failed:', e);
            this.stop();
            return false;
        }
    }

    async initLandmarker() {
        try {
            // Import MediaPipe from local wasm
            const { FilesetResolver, FaceLandmarker } = await import('@mediapipe/tasks-vision');
            
            const vision = await FilesetResolver.forVisionTasks('/mediapipe/wasm');
            this.landmarker = await FaceLandmarker.createFromOptions(vision, {
                baseOptions: { modelAssetPath: '/mediapipe/models/face_landmarker.task' },
                numFaces: 2,
                runningMode: 'VIDEO',
                minFaceDetectionConfidence: 0.5,
            });
            return true;
        } catch (e) {
            console.warn('Failed to load face landmarker:', e);
            return false;
        }
    }

    startDetection() {
        if (!this.landmarker) return;
        this.detecting = true;

        const loop = async () => {
            if (!this.detecting) return;
            if (this.videoEl.readyState >= 2) {
                const result = this.landmarker.detectForVideo(this.videoEl, Date.now());
                this.processDetections(result);
            }
            requestAnimationFrame(loop);
        };
        loop();
        this.heartbeatTimer = window.setInterval(() => {
            this.sendEvent('heartbeat', null);
        }, 10000);
    }

    processDetections(result) {
        const faceCount = result.faceLandmarks.length;

        this.sendEventIfChanged('no_face', faceCount === 0);
        this.sendEventIfChanged('multiple_faces', faceCount > 1, { count: faceCount });

        // Head pose detection: simplified check based on landmark positions
        if (faceCount === 1) {
            const landmarks = result.faceLandmarks[0];
            const noseTip = landmarks[1];
            const leftEye = landmarks[33];
            const rightEye = landmarks[263];

            // Looking away if nose is significantly off-center
            const faceWidth = Math.abs(leftEye.x - rightEye.x);
            const eyeMidpointX = (leftEye.x + rightEye.x) / 2;
            const noseOffsetX = Math.abs(noseTip.x - eyeMidpointX);
            const lookingAway = noseOffsetX > faceWidth * 0.35;

            this.sendEventIfChanged('looking_away', lookingAway);
        }
    }

    sendEventIfChanged(type, detected, meta = null) {
        if (this.lastEvents[type] === detected && !this.retryAt[type]) return;
        if (this.retryAt[type] && this.retryAt[type] > Date.now()) return;
        if (this.pendingEvents[type]) return;

        this.pendingEvents[type] = true;

        this.sendEvent(type, meta, detected);
    }

    sendEvent(type, meta = null, detected = null) {
        fetch(this.eventUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
            body: JSON.stringify({
                session_id: this.sessionId,
                source: 'laptop',
                type,
                meta,
            }),
        }).then(response => {
            if (!response.ok) throw new Error(`Event request failed: ${response.status}`);
            if (detected !== null) {
                this.lastEvents[type] = detected;
                delete this.retryAt[type];
            }
        }).catch(() => {
            if (detected !== null) this.retryAt[type] = Date.now() + 2000;
        }).finally(() => {
            delete this.pendingEvents[type];
        });
    }

    stop() {
        this.detecting = false;
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
        if (this.stream) {
            this.stream.getTracks().forEach(t => t.stop());
        }
        if (this.videoEl) {
            this.videoEl.remove();
        }
    }
}
