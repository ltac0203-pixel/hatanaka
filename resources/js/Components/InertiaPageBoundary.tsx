import ErrorBoundary from "@/Components/ErrorBoundary";
import type { PageProps } from "@inertiajs/core";
import { usePage, type ResolvedComponent } from "@inertiajs/react";
import type { ReactNode } from "react";

interface PageErrorBoundaryProps {
    children: ReactNode;
    pageComponent: string;
    pageKey: number | null;
    pageUrl: string;
}

interface InertiaPageBoundaryProps {
    Component: ResolvedComponent;
    pageKey: number | null;
    props: PageProps;
}

export function PageErrorBoundary({
    children,
    pageComponent,
    pageKey,
    pageUrl,
}: PageErrorBoundaryProps) {
    return (
        <ErrorBoundary resetKeys={[pageComponent, pageUrl, pageKey]}>
            {children}
        </ErrorBoundary>
    );
}

function renderInertiaPage(
    Component: ResolvedComponent,
    props: PageProps,
    pageKey: number | null,
) {
    const page = <Component key={pageKey ?? undefined} {...props} />;

    if (typeof Component.layout === "function") {
        return (Component.layout as (page: ReactNode) => ReactNode)(page);
    }

    if (Array.isArray(Component.layout)) {
        return Component.layout.reduceRight((children, Layout) => {
            return <Layout {...props}>{children}</Layout>;
        }, page);
    }

    return page;
}

export default function InertiaPageBoundary({
    Component,
    pageKey,
    props,
}: InertiaPageBoundaryProps) {
    const page = usePage();

    return (
        <PageErrorBoundary
            pageComponent={page.component}
            pageKey={pageKey}
            pageUrl={page.url}
        >
            {renderInertiaPage(Component, props, pageKey)}
        </PageErrorBoundary>
    );
}
