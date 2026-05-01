import { ReactNode } from "react";

interface ErrorScreenProps {
    badge: string;
    title: string;
    description: string;
    actions: ReactNode;
}

export default function ErrorScreen({
    badge,
    title,
    description,
    actions,
}: ErrorScreenProps) {
    return (
        <div className="relative min-h-screen overflow-hidden bg-black text-white">
            <div className="pointer-events-none absolute inset-0 opacity-30">
                <div className="absolute -left-20 top-10 h-64 w-64 border-2 border-current" />
                <div className="absolute right-12 top-24 h-32 w-32 border-2 border-current" />
                <div className="absolute bottom-16 left-1/3 h-40 w-40 border-2 border-current" />
            </div>

            <div className="relative mx-auto flex min-h-screen max-w-4xl flex-col items-center justify-center px-6 text-center">
                <div className="inline-flex items-center border-2 border-current px-4 py-1 text-sm font-semibold uppercase tracking-[0.2em]">
                    {badge}
                </div>

                <h1 className="mt-8 text-4xl font-bold uppercase tracking-[0.15em] sm:text-6xl">
                    {title}
                </h1>

                <p className="mt-6 max-w-2xl text-base text-gray-300 sm:text-lg">
                    {description}
                </p>

                <div className="mt-10 flex flex-wrap items-center justify-center gap-3">
                    {actions}
                </div>
            </div>
        </div>
    );
}
