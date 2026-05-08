import ApplicationLogo from "@/Components/ApplicationLogo";
import ActionLink from "@/Components/ActionLink";
import FlashToast from "@/Components/FlashToast";
import NavLink from "@/Components/NavLink";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink";
import TextMark from "@/Components/TextMark";
import { useFlashMessage } from "@/hooks/useFlashMessage";
import { usePage } from "@inertiajs/react";
import { PropsWithChildren, ReactNode, useState } from "react";
import { t } from "@/i18n";
import { appRoutes } from "@/utils/routes";

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;
    if (!user) {
        throw new Error(
            "AuthenticatedLayout requires an authenticated user (auth.user is null).",
        );
    }
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const flashProps = useFlashMessage();
    const navMarkClass = "h-5 w-5 text-[10px]";

    const navItems = [
        {
            href: appRoutes.dashboard(),
            label: t("nav.dashboard"),
            active: route().current("dashboard"),
            icon: <TextMark label="D" className={navMarkClass} />,
        },
        {
            href: appRoutes.subscription.index(),
            label: t("nav.subscription"),
            active: route().current("subscription.*"),
            icon: <TextMark label="S" className={navMarkClass} />,
        },
        {
            href: appRoutes.plans.index(),
            label: t("nav.plan"),
            active: route().current("plans.*"),
            icon: <TextMark label="P" className={navMarkClass} />,
        },
        {
            href: appRoutes.cards.create(),
            label: t("nav.card"),
            active: route().current("cards.*"),
            icon: <TextMark label="C" className={navMarkClass} />,
        },
    ];

    return (
        <div className="min-h-screen">
            {/* モバイル表示で背面操作を防ぎ、サイドバーへ操作を集中させる。 */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    role="presentation"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* 主要導線を常に同じ場所に置き、現在地を見失いにくくする。 */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 w-64 transform border-r-2 border-black bg-black text-white transition-transform duration-300 ease-in-out lg:translate-x-0 ${
                    sidebarOpen ? "translate-x-0" : "-translate-x-full"
                }`}
            >
                <div className="flex h-full flex-col">
                    {/* どの画面からでもトップへ戻れる導線を先頭に置く。 */}
                    <div className="flex h-16 items-center px-6 border-b border-gray-800">
                        <ActionLink href={appRoutes.home()} variant="plain">
                            <ApplicationLogo />
                        </ActionLink>
                    </div>

                    {/* 認証後の主要機能を縦導線にまとめ、移動コストを下げる。 */}
                    <nav className="flex-1 space-y-1 px-3 py-4">
                        {navItems.map((item) => (
                            <NavLink
                                key={item.href}
                                href={item.href}
                                active={item.active}
                                icon={item.icon}
                            >
                                {item.label}
                            </NavLink>
                        ))}
                    </nav>

                    {/* 現在の利用者を明示し、誤操作の不安を減らす。 */}
                    <div className="border-t border-gray-800 p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-9 w-9 items-center justify-center border-2 border-white bg-white text-sm font-medium text-black">
                                {user.name?.charAt(0)?.toUpperCase() ?? "?"}
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="truncate text-sm font-medium">
                                    {user.name ?? ""}
                                </p>
                                <p className="truncate text-xs text-gray-400">
                                    {user.email}
                                </p>
                            </div>
                        </div>
                        <ActionLink
                            href={appRoutes.auth.logout()}
                            method="post"
                            as="button"
                            variant="sidebar"
                            className="mt-3"
                        >
                            <TextMark label="->" className="h-4 w-4 text-xs" />
                            {t("nav.logout")}
                        </ActionLink>
                    </div>
                </div>
            </aside>

            {/* メイン領域をサイドバーからずらし、情報が重ならないようにする。 */}
            <div className="lg:pl-64">
                {/* ページ固有タイトルを固定表示し、現在地を即座に把握できるようにする。 */}
                <header className="sticky top-0 z-30 flex h-16 items-center gap-4 border-b-2 border-black bg-white px-4 sm:px-6 lg:px-8">
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="cursor-pointer text-gray-500 lg:hidden"
                    >
                        <TextMark label="=" className="h-6 w-6 text-lg" />
                    </button>
                    {header && <div className="flex-1">{header}</div>}
                    {!header && <div className="flex-1" />}
                    <span className="truncate text-sm text-gray-500">
                        {user.email}
                    </span>
                </header>

                {/* 狭い画面でも主要導線を失わないよう開閉式の補助導線を出す。 */}
                <div className="lg:hidden">
                    {sidebarOpen && (
                        <div className="space-y-1 border-b-2 border-black bg-white pb-3 pt-2">
                            {navItems.map((item) => (
                                <ResponsiveNavLink
                                    key={item.href}
                                    href={item.href}
                                    active={item.active}
                                >
                                    {item.label}
                                </ResponsiveNavLink>
                            ))}
                        </div>
                    )}
                </div>

                <main className="p-4 sm:p-6 lg:p-8">{children}</main>
            </div>
            <FlashToast {...flashProps} />
        </div>
    );
}
