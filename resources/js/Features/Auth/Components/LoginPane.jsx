import { useEffect, useRef, useState } from "react";
import gsap from "gsap";
import { Draggable } from "gsap/Draggable";
import { TextPlugin } from "gsap/TextPlugin";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Card } from "@/Components/ui/card";
import { X, ArrowRight } from "lucide-react";
import { router } from "@inertiajs/react";

gsap.registerPlugin(Draggable, TextPlugin);

const LoginPage = ({ auth, position = "bottom", useStaticPosition = false }) => {
    const [isOpen, setIsOpen] = useState(false);
    const [config] = useState({
        position: position,
        theme: "system",
    });
    const [formData, setFormData] = useState({
        email: "",
        password: "",
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errorMessage, setErrorMessage] = useState("");

    const contentRef = useRef(null);
    const headerRef = useRef(null);
    const innerContentRef = useRef(null);
    const headerTextRef = useRef(null);

    // Set initial theme
    useEffect(() => {
        document.documentElement.dataset.theme = config.theme;
    }, [config.theme]);

    // Initialize header text based on auth state
    useEffect(() => {
        if (headerTextRef.current) {
            headerTextRef.current.textContent = auth?.user ? "Dashboard" : "Sign In";
        }
    }, [auth]);

    // Handle popover animation
    useEffect(() => {
        if (
            !contentRef.current ||
            !innerContentRef.current ||
            !headerRef.current ||
            !headerTextRef.current
        )
            return;

        const tl = gsap.timeline({ defaults: { ease: "power2.out" } });
        const closeButton = headerRef.current.querySelector("button");
        const headerLine = headerRef.current.querySelector("div");
        const headerSpan = headerTextRef.current;

        if (isOpen) {
            // Opening animation
            tl.to(
                contentRef.current,
                {
                    width: 400,
                    height: 280,
                    borderRadius: 12,
                    duration: 0.6,
                },
                0
            )
                .to(headerRef.current, { x: 0, y: 0, duration: 0.6 }, 0)
                // morph the text (only if not logged in, as logged in users won't see this)
                .to(
                    headerSpan,
                    { duration: 0.35, text: "Sign In", ease: "power2.inOut" },
                    0.1
                )
                // optional emphasis
                .fromTo(
                    headerSpan,
                    { opacity: 0.6, scale: 0.98 },
                    { opacity: 1, scale: 1, duration: 0.25 },
                    0.1
                )
                .to(
                    closeButton,
                    { opacity: 1, pointerEvents: "auto", duration: 0.3 },
                    "-=0.2"
                )
                .to(headerLine, { opacity: 1, duration: 0.3 }, "-=0.25")
                .to(
                    innerContentRef.current,
                    {
                        opacity: 1,
                        y: 0,
                        filter: "blur(0px)",
                        pointerEvents: "auto",
                        duration: 0.5,
                    },
                    0.2
                );
        } else {
            // Closing animation
            tl.to(
                innerContentRef.current,
                {
                    opacity: 0,
                    y: 32,
                    filter: "blur(4px)",
                    pointerEvents: "none",
                    duration: 0.2,
                    ease: "power2.in",
                },
                0
            )
                .to(
                    closeButton,
                    { opacity: 0, pointerEvents: "none", duration: 0.2 },
                    "-=0.2"
                )
                .to(headerLine, { opacity: 0, duration: 0.2 }, "-=0.2")
                // morph the text back
                .to(
                    headerSpan,
                    { duration: 0.25, text: auth?.user ? "Dashboard" : "Sign In", ease: "power2.inOut" },
                    0.05
                )
                .fromTo(
                    headerSpan,
                    { opacity: 0.7, scale: 0.98 },
                    { opacity: 1, scale: 1, duration: 0.2 },
                    "<"
                )
                .to(
                    headerRef.current,
                    { x: 0, y: 0, duration: 0.3, ease: "power2.in" },
                    0.1
                )
                .to(
                    contentRef.current,
                    {
                        width: 160,
                        height: 48,
                        borderRadius: 24,
                        duration: 0.3,
                        ease: "power2.in",
                    },
                    "-=0.15"
                );
        }

        return () => tl.kill();
    }, [isOpen, auth]);

    const togglePopover = () => {
        // If logged in, redirect to dashboard instead of opening pane
        if (auth?.user) {
            router.visit('/dashboard');
            return;
        }

        // If not logged in, open the login form
        console.log("LoginPane toggled, isOpen:", !isOpen);
        setIsOpen((v) => !v);

        // Clear error message when closing the pane
        if (isOpen) {
            setErrorMessage("");
        }
    };

    const handleFormSubmit = (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        setErrorMessage("");

        router.post('/login', formData, {
            onFinish: () => setIsSubmitting(false),
            onError: (errors) => {
                console.error('Login failed:', errors);
                setIsSubmitting(false);

                // Set error message based on the error type
                if (errors.email) {
                    setErrorMessage(errors.email);
                } else if (errors.password) {
                    setErrorMessage(errors.password);
                } else {
                    setErrorMessage("Invalid email or password. Please try again.");
                }
            }
        });
    };

    const handleInputChange = (field, value) => {
        setFormData({ ...formData, [field]: value });
        // Clear error when user starts typing
        if (errorMessage) {
            setErrorMessage("");
        }
    };

    const getPositionClasses = () => {
        const positions = {
            center: "top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2",
            top: "top-4 left-1/2 -translate-x-1/2",
            "top-right": "top-4 right-4",
            right: "top-1/2 right-4 -translate-y-1/2",
            "bottom-right": "bottom-4 right-4",
            bottom: "bottom-4 left-1/2 -translate-x-1/2",
            "bottom-left": "bottom-4 left-4",
            left: "top-1/2 left-4 -translate-y-1/2",
            "top-left": "top-4 left-4",
        };
        return positions[config.position] || positions.center;
    };

    return (
        <>
            {/* The expanding button/card - single element that morphs */}
            {/* Only show close button and form when NOT logged in */}
            <Card
                ref={contentRef}
                className={`${useStaticPosition ? 'relative' : 'fixed'} ${useStaticPosition ? '' : getPositionClasses()} overflow-hidden cursor-pointer border shadow-lg backdrop-blur-sm p-0`}
                style={{
                    width: 160,
                    height: 48,
                    borderRadius: 24,
                    zIndex: useStaticPosition ? 'auto' : 9999,
                }}
                onClick={(!isOpen || auth?.user) ? togglePopover : undefined}
            >
                {/* Header */}
                <header
                    ref={headerRef}
                    className="relative h-12 flex items-center justify-center px-6 z-10"
                >
                    <span
                        ref={headerTextRef}
                        className="flex gap-2 items-center text-sm font-medium whitespace-nowrap"
                    />
                    {/* Only show close button when NOT logged in */}
                    {!auth?.user && (
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={(e) => {
                                e.stopPropagation();
                                if (isOpen) togglePopover();
                            }}
                            className="absolute w-9 h-9 opacity-0 pointer-events-none hover:bg-accent rounded-md"
                            style={{
                                right: "8px",
                                top: "50%",
                                transform: "translateY(-50%)",
                            }}
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    )}
                    <div className="absolute bottom-0 left-1/2 -translate-x-1/2 w-full h-px bg-border opacity-0" />
                </header>

                {/* Content - Only show login form when NOT logged in */}
                {!auth?.user && (
                    <div
                        ref={innerContentRef}
                        className="absolute top-[72px] left-0 right-0 px-6 flex flex-col gap-4 opacity-0 pointer-events-none"
                    >
                    {/* Email Form */}
                    <form
                        onSubmit={handleFormSubmit}
                        className="flex flex-col gap-3.5 w-full"
                    >
                        <Input
                            type="email"
                            required
                            placeholder="Enter your email"
                            className={`h-11 w-full ${errorMessage ? 'border-red-500 focus-visible:ring-red-500' : ''}`}
                            value={formData.email}
                            onChange={(e) => handleInputChange('email', e.target.value)}
                            disabled={isSubmitting}
                        />
                        <Input
                            type="password"
                            required
                            placeholder="Enter your password"
                            className={`h-11 w-full ${errorMessage ? 'border-red-500 focus-visible:ring-red-500' : ''}`}
                            value={formData.password}
                            onChange={(e) => handleInputChange('password', e.target.value)}
                            disabled={isSubmitting}
                        />

                        {/* Error Message */}
                        {errorMessage && (
                            <div className="flex items-center gap-2 px-3 py-2 rounded-md bg-red-50 border border-red-200 -mt-2">
                                <svg className="w-4 h-4 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span className="text-xs text-red-700">{errorMessage}</span>
                            </div>
                        )}

                        <Button type="submit" className="w-full h-11 group" disabled={isSubmitting}>
                            <span className="transition-transform group-hover:-translate-x-0.5">
                                {isSubmitting ? 'Signing in...' : 'Continue'}
                            </span>
                            <ArrowRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                        </Button>
                    </form>

                    {/* Help Text */}
                    <p className="m-0.5 flex justify-between text-xs text-muted-foreground -mt-2">
                        <span>Problem signing in?</span>
                        <a
                            href="https://www.google.com"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="underline hover:text-foreground transition-colors"
                        >
                            Contact your administrator
                        </a>
                    </p>
                </div>
                )}
            </Card>
        </>
    );
};

export default LoginPage;
