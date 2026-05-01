import { ButtonHTMLAttributes } from "react";
import { getButtonClassName } from "@/Components/actionStyles";

export default function SecondaryButton({
    type = "button",
    className = "",
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            type={type}
            className={getButtonClassName({
                tone: "secondary",
                disabled,
                className,
            })}
            disabled={disabled}
        >
            {children}
        </button>
    );
}
