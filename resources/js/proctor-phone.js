import { FilesetResolver, ObjectDetector } from '@mediapipe/tasks-vision';

export async function createPhoneDetector() {
    const vision = await FilesetResolver.forVisionTasks('/mediapipe/wasm');

    return ObjectDetector.createFromOptions(vision, {
        baseOptions: { modelAssetPath: '/mediapipe/models/efficientdet_lite0.tflite' },
        scoreThreshold: 0.5,
        runningMode: 'VIDEO',
    });
}
