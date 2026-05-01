import { HTMLAttributes } from "react";

interface TextMarkProps extends HTMLAttributes<HTMLSpanElement> {
    label: string;
    boxed?: boolean;
}

export default function TextMark({
    label,
    boxed = false,
    className = "",
    ...props
}: TextMarkProps) {
    return (
        <span
            aria-hidden="true"
            {...props}
            className={`inline-flex shrink-0 items-center justify-center font-semibold uppercase leading-none ${
                boxed ? "border-2 border-current" : ""
            } ${className}`}
        >
            {label}
        </span>
    );
}
