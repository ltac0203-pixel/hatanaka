import ActionLink from "@/Components/ActionLink";
import { InertiaLinkProps } from "@inertiajs/react";

export default function ResponsiveNavLink({
    active = false,
    className = "",
    children,
    ...props
}: InertiaLinkProps & { active?: boolean }) {
    return (
        <ActionLink
            {...props}
            variant="responsiveNav"
            active={active}
            className={className}
        >
            {children}
        </ActionLink>
    );
}
