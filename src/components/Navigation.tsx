import { useState, useEffect } from "react";
import { Menu, X } from "lucide-react";
import { Button } from "./ui/button";
import { motion, AnimatePresence } from "motion/react";
import Vector from "../imports/Vector";

export function Navigation() {
  const [isScrolled, setIsScrolled] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  useEffect(() => {
    let ticking = false;
    const handleScroll = () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          setIsScrolled(window.scrollY > 100);
          ticking = false;
        });
        ticking = true;
      }
    };

    window.addEventListener("scroll", handleScroll, { passive: true });
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  const scrollToSection = (id: string) => {
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: "smooth" });
      setIsMobileMenuOpen(false);
    }
  };

  const navLinks = [
    { label: "About", id: "about-movement" },
    { label: "Why It Matters", id: "why-matters" },
    { label: "Timeline", id: "timeline" },
    { label: "How to Join", id: "how-to-join" },
    { label: "What's Next", id: "what-happens-next" },
    { label: "Gallery", id: "gallery" },
    { label: "Volunteer", id: "volunteer" },
  ];

  return (
    <nav
      className="fixed top-0 left-0 right-0 z-50 bg-primary backdrop-blur-sm shadow-[var(--elevation-md)] transition-shadow duration-300"
      role="navigation"
      aria-label="Main navigation"
    >
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between h-16 md:h-20">
          <button
            onClick={() => window.scrollTo({ top: 0, behavior: "smooth" })}
            className="flex items-center gap-3 hover:opacity-80 transition-opacity focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-primary rounded-[var(--radius-lg)] p-2"
            aria-label="Scroll to top - Animal Writes home"
          >
            <div className="h-10 w-24 md:h-12 md:w-32" style={{ '--fill-0': 'white' } as React.CSSProperties}>
              <Vector />
            </div>
          </button>

          {/* Desktop Navigation */}
          <div className="hidden md:flex items-center gap-6">
            {navLinks.map((link) => (
              <button
                key={link.id}
                onClick={() => scrollToSection(link.id)}
                className="text-sm text-white/70 hover:text-white transition-colors focus:outline-none focus:text-white focus:underline underline-offset-4"
                aria-label={`Navigate to ${link.label}`}
              >
                {link.label}
              </button>
            ))}
            <Button
              onClick={() => scrollToSection("upload-receipt")}
              className="bg-accent hover:bg-[var(--accent-hover)] text-white focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-primary"
              size="sm"
              aria-label="Navigate to upload receipt section"
            >
              Upload Receipt
            </Button>
            <Button
              onClick={() => scrollToSection("letter-templates")}
              className="bg-white hover:bg-white/90 text-primary focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-primary"
              size="sm"
              aria-label="Navigate to write letter section"
            >
              Write Now
            </Button>
          </div>

          {/* Mobile Menu Button */}
          <button
            onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
            className="md:hidden p-3 text-white/70 hover:text-accent transition-colors focus:outline-none focus:ring-2 focus:ring-white rounded-[var(--radius-lg)]"
            aria-label={isMobileMenuOpen ? "Close menu" : "Open menu"}
            aria-expanded={isMobileMenuOpen}
            aria-controls="mobile-menu"
          >
            {isMobileMenuOpen ? <X className="w-8 h-8" /> : <Menu className="w-8 h-8" />}
          </button>
        </div>

        {/* Mobile Navigation */}
        <AnimatePresence>
          {isMobileMenuOpen && (
            <motion.div
              id="mobile-menu"
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: "auto" }}
              exit={{ opacity: 0, height: 0 }}
              transition={{ duration: 0.3, ease: "easeInOut" }}
              className="md:hidden border-t border-white/10 overflow-hidden"
              role="menu"
            >
              <div className="flex flex-col gap-2 py-6 px-4">
                {navLinks.map((link) => (
                  <button
                    key={link.id}
                    onClick={() => scrollToSection(link.id)}
                    className="text-left px-6 py-4 text-white/70 hover:text-accent hover:bg-white/10 rounded-[var(--radius-xl)] transition-colors focus:outline-none focus:bg-white/10 focus:text-accent"
                    role="menuitem"
                    aria-label={`Navigate to ${link.label}`}
                  >
                    {link.label}
                  </button>
                ))}
                <Button
                  onClick={() => scrollToSection("upload-receipt")}
                  className="bg-accent hover:bg-[var(--accent-hover)] text-white mt-4 py-6 focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-primary"
                  role="menuitem"
                  aria-label="Navigate to upload receipt section"
                >
                  Upload Receipt
                </Button>
                <Button
                  onClick={() => scrollToSection("letter-templates")}
                  className="bg-white hover:bg-white/90 text-primary mt-2 py-6 focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-primary"
                  role="menuitem"
                  aria-label="Navigate to write letter section"
                >
                  Write Now
                </Button>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </nav>
  );
}