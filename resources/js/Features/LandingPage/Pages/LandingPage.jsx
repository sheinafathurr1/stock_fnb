import { Head, router, Link } from "@inertiajs/react";
import { useState, useEffect, useRef } from "react";
import { Button } from "@/Shared/Components/Button";
import LoginPane from "@/Features/Auth/Components/LoginPane";
import gsap from "gsap";

export default function LandingPage({ auth, laravelVersion, phpVersion, outlets = [], flash }) {
    const [selectedOutlet, setSelectedOutlet] = useState(null);
    const [clickAnimation, setClickAnimation] = useState(null);
    const [showError, setShowError] = useState(false);

    // Animation refs
    const navRef = useRef(null);
    const headerRef = useRef(null);
    const gridRef = useRef(null);
    const buttonRef = useRef(null);
    const dividerRef = useRef(null);
    const hintRef = useRef(null);
    const loginPaneRef = useRef(null);

    useEffect(() => {
        if (flash?.error) {
            setShowError(true);
            const timer = setTimeout(() => setShowError(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    // Entrance animations
    useEffect(() => {
        const ctx = gsap.context(() => {
            const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

            // Nav slides down and fades in
            tl.fromTo(
                navRef.current,
                { y: -20, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.6 }
            )
            // Header fades and slides up
            .fromTo(
                headerRef.current,
                { y: 20, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.6 },
                "-=0.4"
            )
            // Outlet cards stagger in with bounce
            .fromTo(
                gridRef.current.children,
                { y: 40, opacity: 0, scale: 0.9 },
                {
                    y: 0,
                    opacity: 1,
                    scale: 1,
                    duration: 0.6,
                    stagger: 0.08,
                    ease: "back.out(1.4)"
                },
                "-=0.4"
            )
            // Continue button fades in with scale
            .fromTo(
                buttonRef.current,
                { opacity: 0, scale: 0.9, y: 10 },
                { opacity: 1, scale: 1, y: 0, duration: 0.5 },
                "-=0.2"
            )
            // OR Divider fades in and expands
            .fromTo(
                dividerRef.current,
                { opacity: 0, scaleX: 0.8 },
                { opacity: 1, scaleX: 1, duration: 0.5 },
                "-=0.2"
            )
            // Hint text fades in
            .fromTo(
                hintRef.current,
                { opacity: 0, y: 10 },
                { opacity: 1, y: 0, duration: 0.4 },
                "-=0.2"
            )
            // Login Pane slides up and fades in
            .fromTo(
                loginPaneRef.current,
                { opacity: 0, y: 20 },
                { opacity: 1, y: 0, duration: 0.5, ease: "back.out(1.2)" },
                "-=0.2"
            );
        });

        return () => ctx.revert();
    }, []);

    const handleOutletClick = (outletId) => {
        setSelectedOutlet(outletId);
        setClickAnimation(outletId);
        setTimeout(() => setClickAnimation(null), 200);
    };

    const handleContinue = () => {
        if (selectedOutlet) {
            const outlet = outlets.find((o) => o.id === selectedOutlet);
            router.visit(
                `/stock-report?outlet=${encodeURIComponent(outlet.name)}`
            );
        }
    };

    return (
        <>
            <Head title="Welcome" />

            {/* Full screen container */}
            <div className="min-h-screen bg-gray-50 flex flex-col">
                {/* Top Navigation Bar */}
                <nav ref={navRef} className="bg-white border-b border-gray-200 shadow-sm flex-shrink-0">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between items-center h-14">
                            {/* Logo/Brand */}
                            <div className="flex items-center gap-3">
                                <div className="w-8 h-8 bg-gray-900 rounded-lg flex items-center justify-center">
                                    <span className="text-white text-lg font-bold">S</span>
                                </div>
                                <div>
                                    <h1 className="text-base font-bold text-gray-900">Stock Report System</h1>
                                    <p className="text-xs text-gray-500">Internal Operations</p>
                                </div>
                            </div>

                            {/* Right side - User info */}
                            {auth.user && (
                                <div className="flex items-center gap-3">
                                    <div className="flex items-center gap-2 px-3 py-1.5 bg-gray-50 rounded-lg border border-gray-200">
                                        <div className="w-7 h-7 bg-gray-900 rounded-full flex items-center justify-center text-white text-xs font-semibold">
                                            {auth.user.name.charAt(0).toUpperCase()}
                                        </div>
                                        <div className="text-left">
                                            <p className="text-sm font-semibold text-gray-900">{auth.user.name}</p>
                                            <p className="text-xs text-gray-500">{auth.user.role}</p>
                                        </div>
                                        <Link
                                            href="/logout"
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
                            )}
                        </div>
                    </div>
                </nav>

                {/* Main Content */}
                <div className="flex-1 flex flex-col justify-center max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-24">
                    {/* Error Alert */}
                    {showError && flash?.error && (
                        <div className="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center justify-between shadow-sm">
                            <div className="flex items-center gap-2">
                                <svg className="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span className="font-medium">{flash.error}</span>
                            </div>
                            <button
                                onClick={() => setShowError(false)}
                                className="text-red-600 hover:text-red-800"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    )}

                    {/* Page Header - More compact */}
                    <div ref={headerRef} className="mb-8">
                        <h2 className="text-2xl font-bold text-gray-900 mb-1">
                            Select Your Outlet
                        </h2>
                        <p className="text-sm text-gray-600">
                            Choose your location to access stock management
                        </p>
                    </div>

                    {/* Outlets Grid - Compact for tablets */}
                    <div ref={gridRef} className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-8">
                        {outlets.map((outlet) => (
                            <button
                                key={outlet.id}
                                onClick={() => handleOutletClick(outlet.id)}
                                className={`
                                    relative p-5 rounded-lg border-2 transition-all duration-200
                                    ${selectedOutlet === outlet.id
                                        ? "border-gray-900 bg-gray-900 text-white shadow-md"
                                        : "border-gray-200 bg-white text-gray-900 hover:border-gray-300 hover:shadow-sm"}
                                    ${clickAnimation === outlet.id ? "scale-95" : ""}
                                    focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2
                                `}
                            >
                                {/* Selection indicator */}
                                {selectedOutlet === outlet.id && (
                                    <div className="absolute top-3 right-3">
                                        <div className="w-5 h-5 bg-white rounded-full flex items-center justify-center">
                                            <svg className="w-3.5 h-3.5 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                    </div>
                                )}

                                {/* Icon */}
                                <div className="mb-3 flex justify-center">
                                    <div className={`
                                        w-14 h-14 rounded-lg flex items-center justify-center text-3xl
                                        ${selectedOutlet === outlet.id ? "bg-white/20" : "bg-gray-100"}
                                    `}>
                                        {outlet.icon}
                                    </div>
                                </div>

                                {/* Name */}
                                <h3 className="text-base font-semibold text-center leading-tight">
                                    {outlet.name}
                                </h3>
                            </button>
                        ))}
                    </div>

                    {/* Continue Button - Centered */}
                    <div ref={buttonRef} className="flex justify-center">
                        <button
                            onClick={handleContinue}
                            disabled={!selectedOutlet}
                            className={`
                                px-8 py-3 rounded-lg font-semibold text-base
                                transition-all duration-200
                                ${selectedOutlet
                                    ? "bg-gray-900 text-white hover:bg-gray-800 shadow-sm hover:shadow-md"
                                    : "bg-gray-300 text-gray-500 cursor-not-allowed"}
                                focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2
                            `}
                        >
                            Continue →
                        </button>
                    </div>

                    {/* OR Divider */}
                    <div ref={dividerRef} className="flex items-center gap-4 mt-12 mb-6">
                        <div className="flex-1 border-t border-gray-300"></div>
                        <span className="text-sm text-gray-500 font-medium">OR</span>
                        <div className="flex-1 border-t border-gray-300"></div>
                    </div>

                    {/* Hint text pointing to LoginPane below */}
                    <div ref={hintRef} className="text-center mb-6">
                        <p className="text-sm text-gray-500">
                            Managers: Sign in below to view reports & schedules
                        </p>
                    </div>

                    {/* Login Pane - Integrated into flow */}
                    <div ref={loginPaneRef} className="flex justify-center">
                        <LoginPane auth={auth} useStaticPosition={true} />
                    </div>
                </div>
            </div>
        </>
    );
}
