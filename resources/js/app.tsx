import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    // The site name from Settings when the page has it; the build-time name otherwise.
    title: (title) => `${title} - ${(window as { __siteName?: string }).__siteName ?? appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);
        (window as { __siteName?: string }).__siteName = (props.initialPage.props.name as string | undefined) ?? undefined;

        root.render(<App {...props} />);
    },
    progress: {
        color: '#1A7538', // the brand's leaf green (--primary)
    },
});

// This will set light / dark mode on load...
initializeTheme();
