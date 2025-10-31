import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// Automatic 419 error handling - reload page to refresh session and CSRF token
router.on('error', (event) => {
    if (event.detail.errors && Object.values(event.detail.errors).some((error: any) => 
        typeof error === 'string' && error.includes('419') || 
        (typeof error === 'object' && error.status === 419)
    )) {
        window.location.reload();
    }
});

// This will set light / dark mode on load...
initializeTheme();
