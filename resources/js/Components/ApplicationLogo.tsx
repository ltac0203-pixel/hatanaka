import { HTMLAttributes } from "react";

interface ApplicationLogoProps extends HTMLAttributes<HTMLDivElement> {
    showWordmark?: boolean;
    markClassName?: string;
    wordmarkClassName?: string;
}

export default function ApplicationLogo({
    className = "",
    showWordmark = true,
    markClassName = "",
    wordmarkClassName = "",
    ...props
}: ApplicationLogoProps) {
    return (
        <div {...props} className={`flex items-center gap-2 ${className}`}>
            <span
                aria-hidden="true"
                className={`relative inline-flex h-8 w-8 shrink-0 items-center justify-center border-2 border-current ${markClassName}`}
            >
                <span className="h-[38%] w-[38%] border-2 border-current" />
                <span className="absolute left-[10%] top-[10%] h-px w-[34%] rotate-45 bg-current" />
                <span className="absolute right-[10%] top-[10%] h-px w-[34%] -rotate-45 bg-current" />
                <span className="absolute left-[10%] bottom-[10%] h-px w-[34%] -rotate-45 bg-current" />
                <span className="absolute right-[10%] bottom-[10%] h-px w-[34%] rotate-45 bg-current" />
            </span>
            {showWordmark && (
                <span
                    className={`font-bold text-xl uppercase tracking-wide ${wordmarkClassName}`}
                >
                    Subscription
                </span>
            )}
        </div>
    );
}
