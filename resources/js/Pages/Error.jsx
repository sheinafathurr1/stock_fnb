import { Head, Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Home, RefreshCw } from "lucide-react";
import { useEffect, useRef } from "react";
import gsap from "gsap";

const ERROR_MESSAGES = {
    400: {
        title: "Bad Request",
        description: "The server couldn't understand your request. Please check and try again.",
    },
    401: {
        title: "Unauthorized",
        description: "You need to be logged in to access this page.",
    },
    403: {
        title: "Forbidden",
        description: "You don't have permission to access this resource.",
    },
    404: {
        title: "Page Not Found",
        description: "The page you're looking for seems to have wandered off into the digital void.",
    },
    419: {
        title: "Session Expired",
        description: "Your session has expired. Please refresh the page and try again.",
    },
    429: {
        title: "Too Many Requests",
        description: "You're making too many requests. Please slow down and try again later.",
    },
    500: {
        title: "Server Error",
        description: "Something went wrong on our end. We're working to fix it.",
    },
    503: {
        title: "Service Unavailable",
        description: "The server is temporarily unavailable. Please try again later.",
    },
};

export default function Error({ status = 500 }) {
    const containerRef = useRef(null);
    const numberRef = useRef(null);
    const contentRef = useRef(null);
    const buttonsRef = useRef(null);

    const errorInfo = ERROR_MESSAGES[status] || {
        title: "Something Went Wrong",
        description: "An unexpected error occurred. Please try again.",
    };

    // Check if this is a session/CSRF error that needs refresh
    const needsRefresh = [419, 429].includes(status);

    useEffect(() => {
        const ctx = gsap.context(() => {
            // Animate the error number
            gsap.from(numberRef.current, {
                scale: 0,
                rotation: -180,
                opacity: 0,
                duration: 1,
                ease: "elastic.out(1, 0.5)",
            });

            // Animate content
            gsap.from(contentRef.current, {
                y: 30,
                opacity: 0,
                duration: 0.8,
                delay: 0.3,
                ease: "power3.out",
            });

            // Animate buttons
            gsap.fromTo(
                buttonsRef.current,
                { y: 20, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.6, delay: 0.6, ease: "power2.out" }
            );

            // Floating animation for the number
            gsap.to(numberRef.current, {
                y: -10,
                duration: 2,
                repeat: -1,
                yoyo: true,
                ease: "power1.inOut",
            });
        }, containerRef);

        return () => ctx.revert();
    }, []);

    const handleRefresh = () => {
        window.location.reload();
    };

    return (
        <>
            <Head title={`${status} - ${errorInfo.title}`} />
            <div
                ref={containerRef}
                className="min-h-screen flex items-center justify-center p-6 bg-gradient-to-br from-background via-background to-muted/20 relative overflow-hidden"
            >
                {/* Animated background elements */}
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                    <div className="absolute top-1/4 left-1/4 w-72 h-72 bg-primary/5 rounded-full blur-3xl"></div>
                    <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-primary/5 rounded-full blur-3xl"></div>
                </div>

                <div className="max-w-2xl w-full mx-auto text-center relative z-10 space-y-8">
                    {/* Error Number */}
                    <div ref={numberRef} className="relative">
                        <div className="inline-flex items-center justify-center">
                            <span className="text-[10rem] md:text-[14rem] font-black bg-gradient-to-br from-primary via-primary to-primary/50 bg-clip-text text-transparent leading-none select-none">
                                {status}
                            </span>
                        </div>
                        <div className="absolute inset-0 flex items-center justify-center opacity-20 blur-2xl">
                            <span className="text-[10rem] md:text-[14rem] font-black text-primary leading-none">
                                {status}
                            </span>
                        </div>
                    </div>

                    {/* Content */}
                    <div ref={contentRef} className="space-y-4 px-4">
                        <h1 className="text-4xl md:text-5xl font-bold tracking-tight">
                            {errorInfo.title}
                        </h1>
                        <p className="text-lg md:text-xl text-muted-foreground max-w-md mx-auto">
                            {errorInfo.description}
                        </p>
                    </div>

                    {/* Buttons */}
                    <div ref={buttonsRef} className="pt-4 flex flex-col sm:flex-row gap-4 justify-center">
                        {needsRefresh && (
                            <Button
                                size="lg"
                                onClick={handleRefresh}
                                className="relative overflow-hidden group px-8 py-6 text-lg font-semibold shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-primary to-primary/90 hover:from-primary hover:to-primary"
                            >
                                <RefreshCw className="mr-2 h-6 w-6 group-hover:rotate-180 transition-transform duration-500" />
                                Refresh Page
                            </Button>
                        )}
                        <Button
                            asChild
                            size="lg"
                            variant={needsRefresh ? "outline" : "default"}
                            className={`relative overflow-hidden group px-8 py-6 text-lg font-semibold shadow-lg hover:shadow-xl transition-all duration-300 ${
                                !needsRefresh
                                    ? "bg-gradient-to-r from-primary to-primary/90 hover:from-primary hover:to-primary"
                                    : ""
                            }`}
                        >
                            <Link href="/" className="relative z-10">
                                <Home className="mr-2 h-6 w-6 group-hover:scale-110 transition-transform duration-300" />
                                Back to Home
                                <span className="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-out -z-10"></span>
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
