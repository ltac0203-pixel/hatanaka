import ActionLink from "@/Components/ActionLink";
import { InertiaLinkProps } from "@inertiajs/react";
import { ReactNode } from "react";

export default function NavLink({
    active = false,
    className = "",
    children,
    icon,
    ...props
}: InertiaLinkProps & { active: boolean; icon?: ReactNode }) {
    return (
        <ActionLink
            {...props}
            variant="nav"
            active={active}
            className={className}
        >
            {icon && <span className="flex-shrink-0">{icon}</span>}
            {children}
        </ActionLink>
    );
}
