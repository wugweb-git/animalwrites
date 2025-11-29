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
      className="relative overflow-hidden bg-[#FFF8F3] min-h-[90vh] flex items-center"
      aria-labelledby="hero-heading"
    >
      {/* Static background elements - removed animations for performance */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div className="absolute top-20 left-10 w-32 h-32 bg-[#F59E0B] rounded-full opacity-5" />
        <div className="absolute bottom-20 right-10 w-40 h-40 bg-[#F59E0B] rounded-full opacity-5" />
      </div>

      {/* Content */}
      <div className="container mx-auto px-4 py-12 md:py-20 relative z-10">
        <div className="max-w-6xl mx-auto">
          <div className="grid md:grid-cols-2 gap-8 md:gap-12 items-center">
            {/* Left Column - Text */}
            <div className="space-y-6 md:space-y-8 text-center md:text-left">
              <div className="space-y-3 md:space-y-4">
                <h1
                  id="hero-heading"
                  className="text-4xl sm:text-5xl md:text-6xl lg:text-7xl leading-tight text-[#333333]"
                >
                  Animal Writes
                </h1>
                <p className="text-lg sm:text-xl md:text-2xl text-[#666666]">
                  India's Largest Letter Petition Campaign
                </p>
              </div>

              <div className="space-y-3 text-base sm:text-lg text-[#666666]">
                <div className="flex items-center gap-3 justify-center md:justify-start">
                  <Mail className="w-5 h-5 text-[#333333]" aria-hidden="true" />
                  <span>29 November 2025</span>
                </div>
                <div className="flex items-center gap-3 justify-center md:justify-start">
                  <div className="inline-flex items-center gap-2 bg-white px-3 py-1.5 rounded-full text-xs md:text-sm border border-gray-200">
                    <Heart className="w-5 h-5 text-[#333333]" aria-hidden="true" />
                  </div>
                  <span>All GPOs and HPOs</span>
                </div>
              </div>

              <p className="text-base sm:text-lg text-[#666666] leading-relaxed max-w-xl mx-auto md:mx-0">
                A nationwide movement of empathy and peaceful civic action. Thousands will walk to their local post offices to mail letters for coexistence, not cruelty.
              </p>

              <div className="flex flex-col sm:flex-row flex-wrap gap-4 justify-center md:justify-start">
                <Button
                  onClick={() => scrollToSection("letter-templates")}
                  className="bg-[#F59E0B] hover:bg-[#D97706] text-white px-6 sm:px-8 py-5 sm:py-6 text-base sm:text-lg shadow-lg hover:shadow-xl transition-all w-full sm:w-auto"
                  aria-label="Navigate to letter templates section"
                >
                  <PenLine className="w-5 h-5 mr-2" aria-hidden="true" />
                  Write Your Letter
                </Button>
                <Button
                  onClick={() => scrollToSection("upload-receipt")}
                  className="bg-[#333333] hover:bg-[#1a1a1a] text-white px-6 sm:px-8 py-5 sm:py-6 text-base sm:text-lg shadow-lg hover:shadow-xl transition-all w-full sm:w-auto border-2 border-[#F59E0B]"
                  aria-label="Navigate to upload receipt section"
                >
                  <Camera className="w-5 h-5 mr-2" aria-hidden="true" />
                  Upload Receipt
                </Button>
              </div>

              <div className="flex items-center gap-2 justify-center md:justify-start text-sm text-[#666666]">
                <span>Want to lead in your city?</span>
                <button
                  onClick={() => scrollToSection("volunteer")}
                  className="text-[#333333] hover:text-[#1a1a1a] underline underline-offset-2 hover:underline-offset-4 transition-all focus:outline-none focus:ring-2 focus:ring-[#333333] focus:ring-offset-2 rounded"
                  aria-label="Navigate to volunteer section"
                >
                  Become a coordinator
                </button>
              </div>

              <p className="text-sm text-[#999999] italic max-w-md mx-auto md:mx-0">
                A small voice. Sealed with compassion. Posted for justice.
              </p>
            </div>

            {/* Right Column - Image */}
            <div className="flex justify-center items-center order-first md:order-last">
              <div className="relative w-full max-w-md">
                <div className="absolute inset-0 bg-[#F59E0B] opacity-10 rounded-3xl blur-2xl" aria-hidden="true" />
                <div className="relative rounded-2xl overflow-hidden shadow-2xl border-4 border-white hover:shadow-3xl transition-shadow bg-gray-200" style={{ width: '100%', maxWidth: '250px', aspectRatio: '1', contentVisibility: 'auto' }}>
                  <picture>
                    <source
                      media="(max-width: 640px)"
                      type="image/webp"
                      srcSet="https://images.unsplash.com/photo-1732812088418-f2fe47b11448?crop=entropy&cs=tinysrgb&fit=max&fm=webp&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxpbmRpYW4lMjBzdHJlZXQlMjBkb2clMjBjb21wYXNzaW9ufGVufDF8fHx8MTc2MzIxOTQyMHww&ixlib=rb-4.1.0&q=25&w=250&auto=format&dpr=1"
                    />
                    <source
                      media="(min-width: 641px)"
                      type="image/webp"
                      srcSet="https://images.unsplash.com/photo-1732812088418-f2fe47b11448?crop=entropy&cs=tinysrgb&fit=max&fm=webp&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxpbmRpYW4lMjBzdHJlZXQlMjBkb2clMjBjb21wYXNzaW9ufGVufDF8fHx8MTc2MzIxOTQyMHww&ixlib=rb-4.1.0&q=50&w=400&auto=format&dpr=1"
                    />
                    <ImageWithFallback
                      src="https://images.unsplash.com/photo-1732812088418-f2fe47b11448?crop=entropy&cs=tinysrgb&fit=max&fm=webp&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxpbmRpYW4lMjBzdHJlZXQlMjBkb2clMjBjb21wYXNzaW9ufGVufDF8fHx8MTc2MzIxOTQyMHww&ixlib=rb-4.1.0&q=50&w=400&auto=format&dpr=1"
                      alt="Indian street dog symbolizing compassion and the animal lives Animal Writes campaign seeks to protect through Supreme Court petition"
                      className="w-full h-full object-cover"
                      loading="eager"
                      fetchpriority="high"
                      width={250}
                      height={250}
                      style={{ display: 'block' }}
                    />
                  </picture>
                  <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-6">
                    <p className="text-white text-center text-lg">
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