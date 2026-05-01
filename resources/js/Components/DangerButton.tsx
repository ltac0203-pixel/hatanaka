import { ButtonHTMLAttributes } from "react";
import { getButtonClassName } from "@/Components/actionStyles";

export default function DangerButton({
    className = "",
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={getButtonClassName({
                tone: "danger",
                disabled,
                className,
            })}
            disabled={disabled}
        >
            {children}
        </button>
    );
}
