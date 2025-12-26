import { useState, useEffect } from "react";

export function ScrollProgress() {
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    let ticking = false;
    const updateProgress = () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          const scrollTop = window.scrollY;
          const docHeight = document.documentElement.scrollHeight - window.innerHeight;
          const scrollPercent = (scrollTop / docHeight) * 100;
          setProgress(scrollPercent);
          ticking = false;
        });
        ticking = true;
      }
    };

    window.addEventListener("scroll", updateProgress, { passive: true });
    updateProgress(); // Initial calculation
    return () => window.removeEventListener("scroll", updateProgress);
  }, []);

  return (
    <div
      className="fixed top-0 left-0 right-0 h-1 bg-gray-200 z-50"
      aria-hidden="true"
    >
      <div
        className="h-full bg-primary transition-all duration-100"
        style={{ width: `${progress}%` }}
      />
    </div>
  );
}