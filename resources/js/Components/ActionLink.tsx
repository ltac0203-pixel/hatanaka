import { InertiaLinkProps, Link } from "@inertiajs/react";
import { PropsWithChildren } from "react";
import {
    ActionLinkVariant,
    ActionSize,
    ActionTone,
    getLinkClassName,
} from "@/Components/actionStyles";

interface ActionLinkProps extends PropsWithChildren, InertiaLinkProps {
    active?: boolean;
    actionSize?: ActionSize;
    className?: string;
    tone?: ActionTone;
    variant?: ActionLinkVariant;
}

export default function ActionLink({
    active = false,
    actionSize = "compact",
    className = "",
    children,
    tone = "primary",
    variant = "plain",
    ...props
}: ActionLinkProps) {
    return (
        <Link
            {...props}
            className={getLinkClassName({
                variant,
                tone,
                size: actionSize,
                active,
                className,
            })}
        >
            {children}
        </Link>
    );
}
