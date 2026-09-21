import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster } from '@/Components/ui/sonner';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        // Try to resolve from both Pages and Features directories
        const pages = import.meta.glob([
            './Pages/**/*.jsx',
            './Features/**/*.jsx'
        ]);

        // Try direct path first
        if (pages[`./Pages/${name}.jsx`]) {
            return pages[`./Pages/${name}.jsx`]();
        }
        if (pages[`./Features/${name}.jsx`]) {
            return pages[`./Features/${name}.jsx`]();
        }

        // Throw error if not found
        throw new Error(`Page not found: ${name}`);
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <>
                <App {...props} />
                <Toaster />
            </>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
