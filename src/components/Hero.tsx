import { Mail, Heart, PenLine, Camera } from "lucide-react";
import { Button } from "./ui/button";
import { ImageWithFallback } from "./figma/ImageWithFallback";

export function Hero() {
  const scrollToSection = (id: string) => {
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: "smooth" });
    }
  };

  return (
    <section
      className="relative overflow-hidden bg-[var(--surface-warm)] min-h-[90vh] flex items-center"
      aria-labelledby="hero-heading"
    >
      {/* Static background elements - removed animations for performance */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div className="absolute top-20 left-10 w-32 h-32 bg-accent rounded-full opacity-5" />
        <div className="absolute bottom-20 right-10 w-40 h-40 bg-accent rounded-full opacity-5" />
      </div>

      {/* Content */}
      <div className="container mx-auto px-6 py-16 md:py-20 relative z-10">
        <div className="max-w-6xl mx-auto">
          <div className="grid md:grid-cols-2 gap-10 md:gap-12 items-center">
            {/* Left Column - Text */}
            <div className="space-y-8 md:space-y-8 text-center md:text-left">
              <div className="space-y-4 md:space-y-4">
                <h1
                  id="hero-heading"
                  className="leading-tight text-primary"
                >
                  Animal Writes
                </h1>
                <p className="text-muted-foreground">
                  India's Largest Letter Petition Campaign
                </p>
              </div>

              <div className="space-y-4 text-muted-foreground">
                <div className="flex items-center gap-3 justify-center md:justify-start">
                  <Mail className="w-6 h-6 text-primary flex-shrink-0" aria-hidden="true" />
                  <span>29 November 2025</span>
                </div>
                <div className="flex items-center gap-3 justify-center md:justify-start">
                  <Heart className="w-6 h-6 text-primary flex-shrink-0" aria-hidden="true" />
                  <span>All GPOs and HPOs</span>
                </div>
              </div>

              <p className="text-muted-foreground leading-relaxed max-w-xl mx-auto md:mx-0">
                A nationwide movement of empathy and peaceful civic action. Thousands will walk to their local post offices to mail letters for coexistence, not cruelty.
              </p>

              <div className="flex flex-col gap-4 justify-center md:justify-start pt-2">
                <Button
                  onClick={() => scrollToSection("letter-templates")}
                  className="bg-accent hover:bg-[var(--accent-hover)] text-white px-8 py-7 shadow-[var(--elevation-lg)] hover:shadow-[var(--elevation-xl)] transition-all w-full"
                  aria-label="Navigate to letter templates section"
                >
                  <PenLine className="w-6 h-6 mr-3" aria-hidden="true" />
                  Write Your Letter
                </Button>
                <Button
                  onClick={() => scrollToSection("upload-receipt")}
                  className="bg-primary hover:bg-[var(--primary-hover)] text-white px-8 py-7 shadow-[var(--elevation-lg)] hover:shadow-[var(--elevation-xl)] transition-all w-full border-2 border-accent"
                  aria-label="Navigate to upload receipt section"
                >
                  <Camera className="w-6 h-6 mr-3" aria-hidden="true" />
                  Upload Receipt
                </Button>
              </div>

              <div className="flex flex-col gap-2 items-center md:items-start text-muted-foreground pt-2">
                <span>Want to lead in your city?</span>
                <button
                  onClick={() => scrollToSection("volunteer")}
                  className="text-primary hover:text-[var(--primary-hover)] underline underline-offset-2 hover:underline-offset-4 transition-all focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded"
                  aria-label="Navigate to volunteer section"
                >
                  Become a coordinator →
                </button>
              </div>

              <p className="text-muted-foreground/70 italic max-w-md mx-auto md:mx-0">
                A small voice. Sealed with compassion. Posted for justice.
              </p>
            </div>

            {/* Right Column - Image */}
            <div className="flex justify-center items-center order-first md:order-last">
              <div className="relative w-full max-w-md">
                <div className="absolute inset-0 bg-accent opacity-10 rounded-[var(--radius-3xl)] blur-2xl" aria-hidden="true" />
                <div className="relative rounded-[var(--radius-2xl)] overflow-hidden shadow-[var(--elevation-2xl)] border-4 border-card hover:shadow-[var(--elevation-3xl)] transition-shadow bg-muted" style={{ width: '100%', maxWidth: '500px', aspectRatio: '1', contentVisibility: 'auto' }}>
                  <picture>
                    <source
                      media="(max-width: 640px)"
                      type="image/webp"
                      srcSet="https://images.unsplash.com/photo-1732812088418-f2fe47b11448?crop=entropy&cs=tinysrgb&fit=max&fm=webp&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxpbmRpYW4lMjBzdHJlZXQlMjBkb2clMjBjb21wYXNzaW9ufGVufDF8fHx8MTc2MzIxOTQyMHww&ixlib=rb-4.1.0&q=40&w=400&auto=format&dpr=1"
                    />
                    <source
                      media="(min-width: 641px)"
                      type="image/webp"
                      srcSet="https://images.unsplash.com/photo-1732812088418-f2fe47b11448?crop=entropy&cs=tinysrgb&fit=max&fm=webp&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxpbmRpYW4lMjBzdHJlZXQlMjBkb2clMjBjb21wYXNzaW9ufGVufDF8fHx8MTc2MzIxOTQyMHww&ixlib=rb-4.1.0&q=50&w=650&auto=format&dpr=1"
                    />
                    <ImageWithFallback
                      src="https://images.unsplash.com/photo-1732812088418-f2fe47b11448?crop=entropy&cs=tinysrgb&fit=max&fm=webp&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxpbmRpYW4lMjBzdHJlZXQlMjBkb2clMjBjb21wYXNzaW9ufGVufDF8fHx8MTc2MzIxOTQyMHww&ixlib=rb-4.1.0&q=50&w=650&auto=format&dpr=1"
                      alt="Indian street dog symbolizing compassion and the animal lives Animal Writes campaign seeks to protect through Supreme Court petition"
                      className="w-full h-full object-cover"
                      loading="eager"
                      fetchpriority="high"
                      width={500}
                      height={500}
                      style={{ display: 'block' }}
                    />
                  </picture>
                  <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-6">
                    <p className="text-white text-center">
                      For Every Voiceless Life
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}