import { useState, useEffect } from "react";
import { Mail, X } from "lucide-react";
import { Button } from "./ui/button";

export function FloatingCTA() {
  const [isVisible, setIsVisible] = useState(false);
  const [isDismissed, setIsDismissed] = useState(false);

  useEffect(() => {
    let ticking = false;
    const handleScroll = () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          if (window.scrollY > 800 && !isDismissed) {
            setIsVisible(true);
          } else if (window.scrollY <= 800) {
            setIsVisible(false);
          }
          ticking = false;
        });
        ticking = true;
      }
    };

    window.addEventListener("scroll", handleScroll, { passive: true });
    return () => window.removeEventListener("scroll", handleScroll);
  }, [isDismissed]);

  const handleDismiss = () => {
    setIsDismissed(true);
  };

  const handleCTAClick = () => {
    const letterSection = document.getElementById("letter-templates");
    letterSection?.scrollIntoView({ behavior: "smooth" });
  };

  if (!isVisible || isDismissed) return null;

  return (
    <div
      className="fixed bottom-20 right-4 left-4 md:left-auto md:bottom-6 md:right-6 z-50 max-w-md md:max-w-lg animate-slide-up"
      role="complementary"
      aria-label="Quick action banner"
    >
      <div className="bg-card rounded-[var(--radius-2xl)] shadow-[var(--elevation-2xl)] p-4 md:p-5 border-2 border-primary/20 relative hover:shadow-[var(--elevation-3xl)] transition-shadow">
        <button
          onClick={handleDismiss}
          className="absolute -top-3 -right-3 bg-primary text-white rounded-[var(--radius-full)] p-2 hover:bg-[var(--primary-hover)] hover:rotate-90 active:scale-90 transition-all focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 shadow-[var(--elevation-lg)]"
          aria-label="Dismiss call to action"
        >
          <X className="w-4 h-4" aria-hidden="true" />
        </button>
        
        <div className="flex flex-col sm:flex-row items-center gap-4">
          <div className="bg-primary rounded-[var(--radius-full)] p-3 flex-shrink-0">
            <Mail className="w-6 h-6 text-white" aria-hidden="true" />
          </div>
          <div className="flex-1 text-center sm:text-left">
            <p className="text-primary">Ready to make a difference?</p>
            <p className="text-xs sm:text-sm text-muted-foreground">Join thousands across India</p>
          </div>
          
          <Button
            className="bg-primary hover:bg-[var(--primary-hover)] text-white whitespace-nowrap focus:ring-2 focus:ring-primary focus:ring-offset-2 hover:scale-105 active:scale-95 transition-transform"
            onClick={handleCTAClick}
            aria-label="Navigate to write letter section"
          >
            Write Your Letter
          </Button>
        </div>
      </div>
    </div>
  );
}