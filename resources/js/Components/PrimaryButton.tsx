import { ButtonHTMLAttributes } from "react";
import { getButtonClassName } from "@/Components/actionStyles";

export default function PrimaryButton({
    className = "",
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={getButtonClassName({
                tone: "primary",
                disabled,
                className,
            })}
            disabled={disabled}
        >
            {children}
        </button>
    );
}
