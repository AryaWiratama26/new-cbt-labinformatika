import { EditorState, Compartment } from '@codemirror/state';
import { EditorView, keymap, lineNumbers, highlightActiveLine, highlightActiveLineGutter, drawSelection, rectangularSelection, crosshairCursor, dropCursor } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { syntaxHighlighting, defaultHighlightStyle, indentOnInput, bracketMatching, foldGutter, foldKeymap } from '@codemirror/language';
import { oneDark } from '@codemirror/theme-one-dark';

const languageLoaders = {
    python: () => import('@codemirror/lang-python').then((m) => m.python()),
    php: () => import('@codemirror/lang-php').then((m) => m.php()),
    javascript: () => import('@codemirror/lang-javascript').then((m) => m.javascript()),
    cpp: () => import('@codemirror/lang-cpp').then((m) => m.cpp()),
    java: () => import('@codemirror/lang-java').then((m) => m.java()),
    sql: () => import('@codemirror/lang-sql').then((m) => m.sql()),
};

const editors = new Map();

async function resolveLanguage(language) {
    const loader = languageLoaders[language];
    if (!loader) {
        return [];
    }

    try {
        return await loader();
    } catch (error) {
        console.warn('[CBTEditor] gagal memuat bahasa:', language, error);
        return [];
    }
}

function baseExtensions(languageCompartment, onChange) {
    return [
        lineNumbers(),
        highlightActiveLineGutter(),
        highlightActiveLine(),
        history(),
        foldGutter(),
        drawSelection(),
        dropCursor(),
        indentOnInput(),
        bracketMatching(),
        rectangularSelection(),
        crosshairCursor(),
        syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
        keymap.of([...defaultKeymap, ...historyKeymap, ...foldKeymap, indentWithTab]),
        languageCompartment.of([]),
        oneDark,
        EditorView.updateListener.of((update) => {
            if (update.docChanged && typeof onChange === 'function') {
                onChange(update.state.doc.toString());
            }
        }),
    ];
}

async function mount(element, options = {}) {
    if (!element || editors.has(element)) {
        return editors.get(element);
    }

    const language = options.language || element.dataset.language || '';
    const source = element.querySelector('.code-input');
    const value = options.value ?? element.dataset.value ?? source?.value ?? element.textContent ?? '';
    const onChange = options.onChange;

    const languageCompartment = new Compartment();

    const view = new EditorView({
        state: EditorState.create({
            doc: value,
            extensions: [
                ...baseExtensions(languageCompartment, onChange),
                EditorView.theme({
                    '&': { height: '18rem', fontSize: '0.875rem' },
                    '.cm-scroller': { fontFamily: "'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace", overflow: 'auto' },
                }),
            ],
        }),
        parent: element,
    });

    editors.set(element, view);

    if (language) {
        const support = await resolveLanguage(language);
        if (support) {
            view.dispatch({ effects: languageCompartment.reconfigure(support) });
        }
    }

    return view;
}

function getValue(element) {
    const view = editors.get(element);
    return view ? view.state.doc.toString() : (element.dataset.value ?? '');
}

function setValue(element, value) {
    const view = editors.get(element);
    if (!view) {
        return;
    }

    view.dispatch({
        changes: { from: 0, to: view.state.doc.length, insert: value },
    });
}

function valueFor(selector) {
    const element = document.querySelector(selector);
    return element ? getValue(element) : '';
}

window.CBTEditor = { mount, getValue, setValue, valueFor };

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.code-editor').forEach((element) => {
        const onChange = element.dataset.autosave === 'true'
            ? (value) => {
                if (typeof window.CBTSaveCode === 'function') {
                    window.CBTSaveCode(element.dataset.qid, value);
                }
            }
            : undefined;

        mount(element, {
            language: element.dataset.language,
            value: element.dataset.value,
            onChange,
        });
    });
});
