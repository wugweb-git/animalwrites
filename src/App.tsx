import { lazy, Suspense } from "react";
import { Hero } from "./components/Hero";
import { Navigation } from "./components/Navigation";
import { CountdownTimer } from "./components/CountdownTimer";
import { AboutMovement } from "./components/AboutMovement";
import { WhyThisMatters } from "./components/WhyThisMatters";
import { HowToJoin } from "./components/HowToJoin";
import { LetterTemplates } from "./components/LetterTemplates";
import { VolunteerSignup } from "./components/VolunteerSignup";
import { FloatingCTA } from "./components/FloatingCTA";
import { ScrollProgress } from "./components/ScrollProgress";
import { MetaTags } from "./components/MetaTags";
import { BackToTop } from "./components/BackToTop";
import { Footer } from "./components/Footer";

// Lazy load above-the-fold but not critical
const VisualImpact = lazy(() => import("./components/VisualImpact").then(m => ({ default: m.VisualImpact })));
const ImpactStats = lazy(() => import("./components/ImpactStats").then(m => ({ default: m.ImpactStats })));
const Timeline = lazy(() => import("./components/Timeline").then(m => ({ default: m.Timeline })));

// Lazy load middle-section components
const ReceiptUpload = lazy(() => import("./components/ReceiptUpload").then(m => ({ default: m.ReceiptUpload })));
const LocalSupport = lazy(() => import("./components/LocalSupport").then(m => ({ default: m.LocalSupport })));
const FAQ = lazy(() => import("./components/FAQ").then(m => ({ default: m.FAQ })));

// Lazy load below-the-fold and heavy components for better performance
const Testimonials = lazy(() => import("./components/Testimonials").then(m => ({ default: m.Testimonials })));
const Supporters = lazy(() => import("./components/Supporters").then(m => ({ default: m.Supporters })));
const CampaignGallery = lazy(() => import("./components/CampaignGallery").then(m => ({ default: m.CampaignGallery })));
const PressMedia = lazy(() => import("./components/PressMedia").then(m => ({ default: m.PressMedia })));

// Lazy load footer area components
const Resources = lazy(() => import("./components/Resources").then(m => ({ default: m.Resources })));
const WhatHappensNext = lazy(() => import("./components/WhatHappensNext").then(m => ({ default: m.WhatHappensNext })));
const SocialShare = lazy(() => import("./components/SocialShare").then(m => ({ default: m.SocialShare })));
const OurMessage = lazy(() => import("./components/OurMessage").then(m => ({ default: m.OurMessage })));

// Lazy load admin pages - rarely accessed
const VolunteerAdmin = lazy(() => import("./components/VolunteerAdmin").then(m => ({ default: m.VolunteerAdmin })));
const ReceiptAdmin = lazy(() => import("./components/ReceiptAdmin").then(m => ({ default: m.ReceiptAdmin })));

// Loading fallback component
const PageLoader = () => (
  <div className="min-h-screen bg-white flex items-center justify-center">
    <div className="text-center">
      <div className="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-[#F59E0B] border-r-transparent"></div>
      <p className="mt-4 text-[#666666]">Loading...</p>
    </div>
  </div>
);

const SectionLoader = () => (
  <div className="py-16 md:py-24 flex items-center justify-center">
    <div className="inline-block h-6 w-6 animate-spin rounded-full border-3 border-solid border-[#F59E0B] border-r-transparent"></div>
  </div>
);

export default function App() {
  // Check if admin page should be shown
  const urlParams = new URLSearchParams(window.location.search);
  const isVolunteerAdmin = urlParams.get('admin') === 'volunteers';
  const isReceiptAdmin = urlParams.get('admin') === 'receipts';

  // Show volunteer admin page if requested
  if (isVolunteerAdmin) {
    return (
      <div className="min-h-screen bg-white">
        <MetaTags />
        <Suspense fallback={<PageLoader />}>
          <VolunteerAdmin />
        </Suspense>
      </div>
    );
  }

  // Show receipt admin page if requested
  if (isReceiptAdmin) {
    return (
      <div className="min-h-screen bg-white">
        <MetaTags />
        <Suspense fallback={<PageLoader />}>
          <ReceiptAdmin />
        </Suspense>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      <MetaTags />
      
      {/* Skip to main content link for keyboard users */}
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-[#333333] focus:text-white focus:rounded-md focus:shadow-lg"
      >
        Skip to main content
      </a>
      
      {/* Scroll progress indicator */}
      <ScrollProgress />
      
      <Navigation />
      
      <main id="main-content" className="pt-16 md:pt-20">
        {/* Hero & Awareness */}
        <Hero />
        <CountdownTimer />
        <AboutMovement />
        
        {/* Why & Context */}
        <WhyThisMatters />
        <Suspense fallback={<SectionLoader />}>
          <VisualImpact />
        </Suspense>
        <Suspense fallback={<SectionLoader />}>
          <ImpactStats />
        </Suspense>
        <Suspense fallback={<SectionLoader />}>
          <Timeline />
        </Suspense>
        
        {/* How to Participate */}
        <HowToJoin />
        <LetterTemplates />
        <Suspense fallback={<SectionLoader />}>
          <ReceiptUpload />
        </Suspense>
        <Suspense fallback={<SectionLoader />}>
          <LocalSupport />
        </Suspense>
        <Suspense fallback={<SectionLoader />}>
          <FAQ />
        </Suspense>
        
        {/* Get Involved */}
        <VolunteerSignup />
        
        {/* Social Proof & Community - Lazy loaded for performance */}
        <Suspense fallback={<SectionLoader />}>
          <Testimonials />
        </Suspense>
        <Suspense fallback={<SectionLoader />}>
          <Supporters />
        </Suspense>
        <Suspense fallback={<SectionLoader />}>
          <CampaignGallery />
        </Suspense>
        <Suspense fallback={<SectionLoader />}>
          <PressMedia />
        </Suspense>
        
        {/* Resources & Next Steps */}
        <Suspense fallback={<SectionLoader />}>
          <Resources />
        </Suspense>
        <Suspense fallback={<SectionLoader />}>
          <WhatHappensNext />
        </Suspense>
        <Suspense fallback={<SectionLoader />}>
          <SocialShare />
        </Suspense>
        <Suspense fallback={<SectionLoader />}>
          <OurMessage />
        </Suspense>
        
        {/* Footer & Utils */}
        <Footer />
        <FloatingCTA />
        <BackToTop />
      </main>
    </div>
  );
}