import { useState, useEffect } from "react";
import { Menu, X } from "lucide-react";
import { Button } from "./ui/button";
import { motion, AnimatePresence } from "motion/react";
import { Logo } from "./Logo";

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
      className="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-sm shadow-sm transition-shadow duration-300"
      role="navigation"
      aria-label="Main navigation"
    >
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between h-16 md:h-20">
          <button
            onClick={() => window.scrollTo({ top: 0, behavior: "smooth" })}
            className="flex items-center gap-2 hover:opacity-80 transition-opacity focus:outline-none focus:ring-2 focus:ring-[#333333] focus:ring-offset-2 rounded-lg p-1"
            aria-label="Scroll to top - Animal Writes home"
          >
            <Logo 
              size={64}
              className="h-12 w-12 md:h-16 md:w-16"
            />
            <span className="text-[#333333] tracking-wide text-base md:text-lg hidden sm:inline">ANIMAL WRITES</span>
          </button>

          {/* Desktop Navigation */}
          <div className="hidden md:flex items-center gap-6">
            {navLinks.map((link) => (
              <button
                key={link.id}
                onClick={() => scrollToSection(link.id)}
                className="text-sm text-[#666666] hover:text-[#333333] transition-colors focus:outline-none focus:text-[#333333] focus:underline underline-offset-4"
                aria-label={`Navigate to ${link.label}`}
              >
                {link.label}
              </button>
            ))}
            <Button
              onClick={() => scrollToSection("upload-receipt")}
              className="bg-[#F59E0B] hover:bg-[#D97706] text-white focus:ring-2 focus:ring-[#F59E0B] focus:ring-offset-2"
              size="sm"
              aria-label="Navigate to upload receipt section"
            >
              Upload Receipt
            </Button>
            <Button
              onClick={() => scrollToSection("letter-templates")}
              className="bg-[#333333] hover:bg-[#1a1a1a] text-white focus:ring-2 focus:ring-[#333333] focus:ring-offset-2"
              size="sm"
              aria-label="Navigate to write letter section"
            >
              Write Now
            </Button>
          </div>

          {/* Mobile Menu Button */}
          <button
            onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
            className="md:hidden p-3 text-[#666666] hover:text-[#D97706] transition-colors focus:outline-none focus:ring-2 focus:ring-[#333333] rounded-lg"
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
              className="md:hidden border-t border-gray-100 overflow-hidden"
              role="menu"
            >
              <div className="flex flex-col gap-2 py-6 px-4">
                {navLinks.map((link) => (
                  <button
                    key={link.id}
                    onClick={() => scrollToSection(link.id)}
                    className="text-left px-6 py-4 text-lg text-[#666666] hover:text-[#D97706] hover:bg-[#FFF8F3] rounded-xl transition-colors focus:outline-none focus:bg-[#FFF8F3] focus:text-[#D97706]"
                    role="menuitem"
                    aria-label={`Navigate to ${link.label}`}
                  >
                    {link.label}
                  </button>
                ))}
                <Button
                  onClick={() => scrollToSection("upload-receipt")}
                  className="bg-[#F59E0B] hover:bg-[#D97706] text-white mt-4 py-6 text-lg focus:ring-2 focus:ring-[#F59E0B] focus:ring-offset-2"
                  role="menuitem"
                  aria-label="Navigate to upload receipt section"
                >
                  Upload Receipt
                </Button>
                <Button
                  onClick={() => scrollToSection("letter-templates")}
                  className="bg-[#333333] hover:bg-[#1a1a1a] text-white mt-2 py-6 text-lg focus:ring-2 focus:ring-[#333333] focus:ring-offset-2"
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