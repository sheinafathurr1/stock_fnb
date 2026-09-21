import { Head, Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Home } from "lucide-react";
import { useEffect, useRef } from "react";
import gsap from "gsap";

export default function NotFound({ requestedUrl }) {
  const containerRef = useRef(null);
  const numberRef = useRef(null);
  const contentRef = useRef(null);
  const buttonsRef = useRef(null);

  useEffect(() => {
    const ctx = gsap.context(() => {
      // Animate the 404 number
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

      // Animate button
      gsap.fromTo(
        buttonsRef.current,
        { y: 20, opacity: 0 },
        { y: 0, opacity: 1, duration: 0.6, delay: 0.6, ease: "power2.out" }
      );

      // Floating animation for the 404
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

  return (
    <>
      <Head title="404 - Page Not Found" />
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
          {/* 404 Number */}
          <div ref={numberRef} className="relative">
            <div className="inline-flex items-center justify-center">
              <span className="text-[12rem] md:text-[16rem] font-black bg-gradient-to-br from-primary via-primary to-primary/50 bg-clip-text text-transparent leading-none select-none">
                404
              </span>
            </div>
            <div className="absolute inset-0 flex items-center justify-center opacity-20 blur-2xl">
              <span className="text-[12rem] md:text-[16rem] font-black text-primary leading-none">
                404
              </span>
            </div>
          </div>

          {/* Content */}
          <div ref={contentRef} className="space-y-4 px-4">
            <h1 className="text-4xl md:text-5xl font-bold tracking-tight">
              Oops! Page Not Found
            </h1>
            <p className="text-lg md:text-xl text-muted-foreground max-w-md mx-auto">
              The page you're looking for seems to have wandered off into the digital void.
            </p>
          </div>

          {/* Button */}
          <div ref={buttonsRef} className="pt-4">
            <Button
              asChild
              size="lg"
              className="relative overflow-hidden group px-8 py-6 text-lg font-semibold shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-primary to-primary/90 hover:from-primary hover:to-primary"
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