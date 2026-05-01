import ApplicationLogo from "@/Components/ApplicationLogo";
import ActionLink from "@/Components/ActionLink";
import { PropsWithChildren, ReactNode } from "react";
import { appRoutes } from "@/utils/routes";

interface GuestLayoutProps extends PropsWithChildren {
    footer?: ReactNode;
}

export default function Guest({ footer, children }: GuestLayoutProps) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-black px-4 py-6 sm:px-6">
            <div className="w-full max-w-md border-2 border-black bg-white p-6 text-black sm:p-8">
                <div className="mb-8 flex justify-center">
                    <ActionLink
                        href={appRoutes.home()}
                        variant="plain"
                    >
                        <ApplicationLogo />
                    </ActionLink>
                </div>

                <div>{children}</div>

                {footer && (
                    <div className="mt-8 border-t border-gray-200 pt-5">
                        {footer}
                    </div>
                )}
            </div>
        </div>
    );
}
