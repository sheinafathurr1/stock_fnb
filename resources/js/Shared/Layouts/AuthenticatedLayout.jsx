import ApplicationLogo from '@/Shared/Components/ApplicationLogo';
import NavLink from '@/Shared/Components/NavLink';
import ResponsiveNavLink from '@/Shared/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { LayoutDashboard, LogOut, Menu, X, FileText } from 'lucide-react';

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const isManager =
        typeof user?.role === 'string' && user.role.toLowerCase() === 'manager';

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    return (
        <div className="min-h-screen bg-gray-50">
            <nav className="sticky top-0 z-50 border-b border-gray-200/80 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-14 items-center justify-between">
                        {/* Logo & Nav */}
                        <div className="flex items-center gap-8">
                            <Link href="/" className="flex items-center gap-2 group">
                                <ApplicationLogo className="h-7 w-auto fill-current text-gray-800 transition-transform group-hover:scale-105" />
                                <span className="hidden sm:inline-block text-sm font-semibold text-gray-900">
                                    Stock Report
                                </span>
                            </Link>

                            <div className="hidden md:flex items-center gap-1">
                                <Button 
                                    asChild 
                                    variant={route().current('dashboard') ? 'default' : 'ghost'} 
                                    size="sm"
                                    className="gap-2"
                                >
                                    <Link href={route('dashboard')}>
                                        <LayoutDashboard className="h-4 w-4" />
                                        Dashboard
                                    </Link>
                                </Button>
                                {isManager && (
                                    <>
                                        <Button
                                            asChild
                                            variant={route().current('reports.index') ? 'default' : 'ghost'}
                                            size="sm"
                                            className="gap-2"
                                        >
                                            <Link href={route('reports.index')}>
                                                <FileText className="h-4 w-4" />
                                                Reports
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            variant={route().current('schedule.index') ? 'default' : 'ghost'}
                                            size="sm"
                                            className="gap-2"
                                        >
                                            <Link href={route('schedule.index')}>
                                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                Schedule
                                            </Link>
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>

                        {/* User Menu - Desktop */}
                        <div className="hidden md:flex items-center gap-3">
                            <div className="flex items-center gap-2 px-3 py-1.5 bg-gray-50 rounded-lg border border-gray-200">
                                <div className="w-7 h-7 bg-gray-900 rounded-full flex items-center justify-center text-white text-xs font-semibold">
                                    {user.name.charAt(0).toUpperCase()}
                                </div>
                                <div className="text-left">
                                    <p className="text-sm font-semibold text-gray-900">{user.name}</p>
                                    <p className="text-xs text-gray-500">{user.role}</p>
                                </div>
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className="ml-1 text-gray-400 hover:text-red-600 transition-colors"
                                    title="Logout"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                </Link>
                            </div>
                        </div>

                        {/* Mobile Menu Button */}
                        <div className="flex md:hidden">
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                            >
                                {showingNavigationDropdown ? (
                                    <X className="h-5 w-5" />
                                ) : (
                                    <Menu className="h-5 w-5" />
                                )}
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Mobile Menu */}
                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' border-t border-gray-200 md:hidden'
                    }
                >
                    <div className="space-y-1 px-4 pb-3 pt-2">
                        <Button 
                            asChild 
                            variant={route().current('dashboard') ? 'default' : 'ghost'} 
                            size="sm"
                            className="w-full justify-start gap-2"
                        >
                            <Link href={route('dashboard')}>
                                <LayoutDashboard className="h-4 w-4" />
                                Dashboard
                            </Link>
                        </Button>
                        {isManager && (
                            <>
                                <Button
                                    asChild
                                    variant={route().current('reports.index') ? 'default' : 'ghost'}
                                    size="sm"
                                    className="w-full justify-start gap-2"
                                >
                                    <Link href={route('reports.index')}>
                                        <FileText className="h-4 w-4" />
                                        Reports
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    variant={route().current('schedule.index') ? 'default' : 'ghost'}
                                    size="sm"
                                    className="w-full justify-start gap-2"
                                >
                                    <Link href={route('schedule.index')}>
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Schedule
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>

                    <div className="border-t border-gray-200 px-4 pb-3 pt-4">
                        <div className="flex items-center gap-3 mb-3">
                            <div className="w-10 h-10 bg-gray-900 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                {user.name.charAt(0).toUpperCase()}
                            </div>
                            <div className="flex-1">
                                <div className="text-sm font-medium text-gray-800">
                                    {user.name}
                                </div>
                                <div className="text-xs text-gray-500">
                                    {user.role}
                                </div>
                            </div>
                        </div>

                        <div className="space-y-1">
                            <Button
                                asChild
                                variant="ghost"
                                size="sm"
                                className="w-full justify-start gap-2 text-red-600 hover:text-red-700 hover:bg-red-50"
                            >
                                <Link method="post" href={route('logout')} as="button">
                                    <LogOut className="h-4 w-4" />
                                    Log Out
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="border-b border-gray-200 bg-white">
                    <div className="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">{children}</main>
        </div>
    );
}
