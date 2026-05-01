import "../css/app.css";
import "./bootstrap";

import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";
import InertiaPageBoundary from "@/Components/InertiaPageBoundary";

void createInertiaApp({
    title: (title) => title,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob(["./Pages/**/*.tsx", "!./Pages/**/*.test.tsx"]),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <App {...props}>
                {({ Component, props: pageProps, key }) => (
                    <InertiaPageBoundary
                        Component={Component}
                        pageKey={key}
                        props={pageProps}
                    />
                )}
            </App>,
        );
    },
    progress: {
        color: "#4B5563",
    },
});
