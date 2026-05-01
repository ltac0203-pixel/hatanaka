import { SelectHTMLAttributes } from "react";

export default function SelectInput({
    className = "",
    children,
    ...props
}: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select {...props} className={`app-form-control ${className}`.trim()}>
            {children}
        </select>
    );
}
