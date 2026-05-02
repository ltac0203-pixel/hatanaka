import "../css/app.css";
import "./bootstrap";

import { createInertiaApp, type ResolvedComponent } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";
import InertiaPageBoundary from "@/Components/InertiaPageBoundary";

void createInertiaApp({
    title: (title) => title,
    resolve: async (name) => {
        const pages = import.meta.glob<{ default: ResolvedComponent }>(
            ["./Pages/**/*.tsx", "!./Pages/**/*.test.tsx"],
        );
        const module = await resolvePageComponent(`./Pages/${name}.tsx`, pages);
        return module.default;
    },
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
