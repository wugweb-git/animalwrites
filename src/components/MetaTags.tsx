import { useEffect } from "react";

export function MetaTags() {
  useEffect(() => {
    // Set document title
    document.title = "Animal Writes - India's Largest Letter Petition Campaign for Street Dogs";
    
    // Set HTML lang attribute
    document.documentElement.lang = "en";

    // Install Google Analytics (gtag.js)
    const installGtag = () => {
      // Check if script already exists
      if (document.querySelector('script[src*="googletagmanager.com/gtag/js"]')) {
        return;
      }

      // Create and append gtag script
      const script = document.createElement('script');
      script.async = true;
      script.src = 'https://www.googletagmanager.com/gtag/js?id=G-4P5YMJ04RL';
      document.head.appendChild(script);

      // Initialize gtag
      const inlineScript = document.createElement('script');
      inlineScript.innerHTML = `
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-4P5YMJ04RL');
      `;
      document.head.appendChild(inlineScript);
    };

    installGtag();

    // Set favicon - Multiple formats for better browser compatibility
    const setFavicon = () => {
      const logoUrl = "https://vedanshu.design/AW/logo";
      
      // Standard favicon
      let link = document.querySelector("link[rel*='icon']") as HTMLLinkElement;
      if (!link) {
        link = document.createElement("link");
        link.rel = "icon";
        document.head.appendChild(link);
      }
      link.href = logoUrl;
      
      // Shortcut icon (for older browsers)
      let shortcutIcon = document.querySelector("link[rel='shortcut icon']") as HTMLLinkElement;
      if (!shortcutIcon) {
        shortcutIcon = document.createElement("link");
        shortcutIcon.rel = "shortcut icon";
        document.head.appendChild(shortcutIcon);
      }
      shortcutIcon.href = logoUrl;
      
      // Icon with type specified
      let typedIcon = document.querySelector("link[rel='icon'][type]") as HTMLLinkElement;
      if (!typedIcon) {
        typedIcon = document.createElement("link");
        typedIcon.rel = "icon";
        typedIcon.type = "image/png";
        document.head.appendChild(typedIcon);
      }
      typedIcon.href = logoUrl;
      
      // Apple touch icon (also uses the logo)
      let appleTouchIcon = document.querySelector("link[rel='apple-touch-icon']:not([sizes])") as HTMLLinkElement;
      if (!appleTouchIcon) {
        appleTouchIcon = document.createElement("link");
        appleTouchIcon.rel = "apple-touch-icon";
        document.head.appendChild(appleTouchIcon);
      }
      appleTouchIcon.href = logoUrl;
    };
    
    setFavicon();

    // Resource Hints for Performance
    const addResourceHints = () => {
      // DNS Prefetch for external domains
      const dnsPrefetchDomains = [
        'https://www.googletagmanager.com',
        'https://www.google-analytics.com',
        'https://vedanshu.design',
        'https://images.unsplash.com'
      ];

      dnsPrefetchDomains.forEach(domain => {
        if (!document.querySelector(`link[rel="dns-prefetch"][href="${domain}"]`)) {
          const link = document.createElement('link');
          link.rel = 'dns-prefetch';
          link.href = domain;
          document.head.appendChild(link);
        }
      });

      // Preconnect to critical third-party origins
      const preconnectOrigins = [
        { href: 'https://www.googletagmanager.com', crossorigin: false },
        { href: 'https://vedanshu.design', crossorigin: true }
      ];

      preconnectOrigins.forEach(origin => {
        if (!document.querySelector(`link[rel="preconnect"][href="${origin.href}"]`)) {
          const link = document.createElement('link');
          link.rel = 'preconnect';
          link.href = origin.href;
          if (origin.crossorigin) {
            link.crossOrigin = 'anonymous';
          }
          document.head.appendChild(link);
        }
      });

      // Preload critical images
      const preloadImage = (href: string) => {
        if (!document.querySelector(`link[rel="preload"][href="${href}"]`)) {
          const link = document.createElement('link');
          link.rel = 'preload';
          link.as = 'image';
          link.href = href;
          document.head.appendChild(link);
        }
      };

      // Preload hero image with WebP for better performance - smaller size for faster LCP
      preloadImage('https://images.unsplash.com/photo-1732812088418-f2fe47b11448?crop=entropy&cs=tinysrgb&fit=max&fm=webp&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxpbmRpYW4lMjBzdHJlZXQlMjBkb2clMjBjb21wYXNzaW9ufGVufDF8fHx8MTc2MzIxOTQyMHww&ixlib=rb-4.1.0&q=80&w=600');
      // Preload logo
      preloadImage('https://vedanshu.design/AW/logo');
    };

    addResourceHints();

    // Create or update meta tags
    const setMetaTag = (name: string, content: string, property?: boolean) => {
      const attribute = property ? "property" : "name";
      let meta = document.querySelector(`meta[${attribute}="${name}"]`) as HTMLMetaElement;
      
      if (!meta) {
        meta = document.createElement("meta");
        meta.setAttribute(attribute, name);
        document.head.appendChild(meta);
      }
      
      meta.content = content;
    };

    // Basic meta tags
    setMetaTag("description", "Join India's largest citizen-led letter petition movement on November 29, 2025. Walk to your nearest GPO/HPO and post letters to the Supreme Court advocating for humane coexistence with street dogs.");
    setMetaTag("keywords", "animal rights India, street dogs petition, Supreme Court India, animal welfare campaign, save Indian street dogs, GPO letter campaign, animal rights movement, compassionate coexistence, ABC program India, stray dog relocation, letter petition India, animal activism, street dog welfare, November 29 campaign");
    setMetaTag("author", "Animal Writes Movement");
    setMetaTag("viewport", "width=device-width, initial-scale=1.0");
    setMetaTag("robots", "index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1");
    setMetaTag("googlebot", "index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1");
    setMetaTag("bingbot", "index, follow");
    
    // Geographic targeting
    setMetaTag("geo.region", "IN");
    setMetaTag("geo.placename", "India");
    setMetaTag("geo.position", "20.593684;78.96288");
    
    // Language and content
    setMetaTag("language", "English");
    setMetaTag("revisit-after", "7 days");
    setMetaTag("rating", "General");
    setMetaTag("distribution", "global");
    
    // Content categorization
    setMetaTag("category", "Animal Rights, Social Activism, Petition Campaign");
    setMetaTag("coverage", "Worldwide");
    setMetaTag("target", "all");
    
    // Article/Publication meta
    setMetaTag("article:publisher", "Animal Writes Movement", true);
    setMetaTag("article:author", "Animal Writes Team", true);
    setMetaTag("article:section", "Animal Rights", true);
    setMetaTag("article:tag", "animal welfare, street dogs, petition, India, Supreme Court", true);
    
    // Canonical URL
    const setCanonicalUrl = () => {
      let link = document.querySelector("link[rel='canonical']") as HTMLLinkElement;
      if (!link) {
        link = document.createElement("link");
        link.rel = "canonical";
        document.head.appendChild(link);
      }
      link.href = window.location.origin + window.location.pathname;
    };
    setCanonicalUrl();
    
    // Add alternate links for sitemap
    const setSitemapLink = () => {
      let link = document.querySelector("link[rel='sitemap']") as HTMLLinkElement;
      if (!link) {
        link = document.createElement("link");
        link.rel = "sitemap";
        link.type = "application/xml";
        link.title = "Sitemap";
        document.head.appendChild(link);
      }
      link.href = `${window.location.origin}/sitemap.xml`;
    };
    setSitemapLink();
    
    // Add Web App Manifest
    const setManifestLink = () => {
      let link = document.querySelector("link[rel='manifest']") as HTMLLinkElement;
      if (!link) {
        link = document.createElement("link");
        link.rel = "manifest";
        document.head.appendChild(link);
      }
      link.href = `${window.location.origin}/manifest.json`;
    };
    setManifestLink();
    
    // Social sharing image
    const ogImageUrl = "https://vedanshu.design/AW/logo";
    
    // Open Graph tags for social sharing
    setMetaTag("og:title", "Animal Writes - India's Largest Letter Petition Campaign for Street Dogs", true);
    setMetaTag("og:description", "Join thousands across India on Nov 29, 2025. Walk to your GPO and write to the Supreme Court for humane coexistence with street dogs. Be part of India's largest citizen-led animal rights movement.", true);
    setMetaTag("og:type", "website", true);
    setMetaTag("og:url", window.location.href, true);
    setMetaTag("og:image", ogImageUrl, true);
    setMetaTag("og:image:width", "1200", true);
    setMetaTag("og:image:height", "630", true);
    setMetaTag("og:image:alt", "Animal Writes - India's Largest Letter Petition Campaign Logo", true);
    setMetaTag("og:site_name", "Animal Writes", true);
    setMetaTag("og:locale", "en_IN", true);
    
    // Twitter Card tags
    setMetaTag("twitter:card", "summary_large_image");
    setMetaTag("twitter:title", "Animal Writes - India's Largest Letter Petition Campaign");
    setMetaTag("twitter:description", "Join thousands across India on Nov 29, 2025. Write for coexistence, not cruelty. Walk to your GPO and be part of history. #SaveIndianStreetDogs #AnimalWrites");
    setMetaTag("twitter:image", ogImageUrl);
    setMetaTag("twitter:image:alt", "Animal Writes - India's Largest Letter Petition Campaign Logo");
    
    // Theme color - Changed to yellow
    setMetaTag("theme-color", "#F59E0B");
    
    // Mobile app capable
    setMetaTag("mobile-web-app-capable", "yes");
    setMetaTag("apple-mobile-web-app-capable", "yes");
    setMetaTag("apple-mobile-web-app-status-bar-style", "default");
    setMetaTag("apple-mobile-web-app-title", "Animal Writes");
    
    // Security and Privacy Headers
    setMetaTag("referrer", "strict-origin-when-cross-origin");
    setMetaTag("format-detection", "telephone=no");
    
    // HTTP-EQUIV Meta Tags for additional technical SEO
    const setHttpEquivTag = (httpEquiv: string, content: string) => {
      let meta = document.querySelector(`meta[http-equiv="${httpEquiv}"]`) as HTMLMetaElement;
      if (!meta) {
        meta = document.createElement("meta");
        meta.httpEquiv = httpEquiv;
        document.head.appendChild(meta);
      }
      meta.content = content;
    };
    
    setHttpEquivTag("X-UA-Compatible", "IE=edge");
    setHttpEquivTag("content-type", "text/html; charset=UTF-8");
    
    // Add JSON-LD Structured Data
    const addStructuredData = () => {
      // Remove existing schema if present
      const existingSchema = document.querySelector('script[type="application/ld+json"]');
      if (existingSchema) {
        existingSchema.remove();
      }

      const structuredData = {
        "@context": "https://schema.org",
        "@graph": [
          {
            "@type": "Organization",
            "@id": `${window.location.origin}/#organization`,
            "name": "Animal Writes",
            "url": window.location.origin,
            "logo": {
              "@type": "ImageObject",
              "url": "https://vedanshu.design/AW/logo",
              "width": 512,
              "height": 512
            },
            "description": "India's largest citizen-led letter petition movement for animal rights and humane coexistence with street dogs",
            "foundingDate": "2025",
            "contactPoint": {
              "@type": "ContactPoint",
              "contactType": "Customer Service",
              "availableLanguage": ["English", "Hindi"]
            },
            "sameAs": [
              "https://www.instagram.com/animalwrites.in/",
              "https://www.facebook.com/share/g/15h2tcaWvM/",
              "https://x.com/AnimalWritesIN"
            ]
          },
          {
            "@type": "WebSite",
            "@id": `${window.location.origin}/#website`,
            "url": window.location.origin,
            "name": "Animal Writes",
            "description": "Join India's largest letter petition movement for animal rights",
            "publisher": {
              "@id": `${window.location.origin}/#organization`
            },
            "potentialAction": {
              "@type": "SearchAction",
              "target": {
                "@type": "EntryPoint",
                "urlTemplate": `${window.location.origin}/?s={search_term_string}`
              },
              "query-input": "required name=search_term_string"
            },
            "inLanguage": "en-IN"
          },
          {
            "@type": "WebPage",
            "@id": `${window.location.href}#webpage`,
            "url": window.location.href,
            "name": "Animal Writes - India's Largest Letter Petition Campaign for Street Dogs",
            "isPartOf": {
              "@id": `${window.location.origin}/#website`
            },
            "about": {
              "@id": `${window.location.origin}/#organization`
            },
            "datePublished": "2025-11-01T00:00:00+05:30",
            "dateModified": new Date().toISOString(),
            "description": "Join India's largest citizen-led letter petition movement on November 29, 2025. Walk to your nearest GPO/HPO and post letters to the Supreme Court advocating for humane coexistence with street dogs.",
            "inLanguage": "en-IN"
          },
          {
            "@type": "BreadcrumbList",
            "@id": `${window.location.href}#breadcrumb`,
            "itemListElement": [
              {
                "@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": window.location.origin
              },
              {
                "@type": "ListItem",
                "position": 2,
                "name": "Campaign",
                "item": `${window.location.origin}/#about`
              },
              {
                "@type": "ListItem",
                "position": 3,
                "name": "How to Join",
                "item": `${window.location.origin}/#how-to-join`
              }
            ]
          },
          {
            "@type": "Event",
            "@id": `${window.location.origin}/#event`,
            "name": "Animal Writes - National GPO Letter Campaign",
            "description": "Thousands of citizens across India will walk to their nearest GPO/HPO to post handwritten letters to the Supreme Court of India advocating for humane coexistence with street dogs",
            "startDate": "2025-11-29T09:00:00+05:30",
            "endDate": "2025-11-29T17:00:00+05:30",
            "eventStatus": "https://schema.org/EventScheduled",
            "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
            "location": {
              "@type": "Place",
              "name": "General Post Offices and Head Post Offices across India",
              "address": {
                "@type": "PostalAddress",
                "addressCountry": "IN"
              }
            },
            "organizer": {
              "@id": `${window.location.origin}/#organization`
            },
            "image": "https://vedanshu.design/AW/logo",
            "offers": {
              "@type": "Offer",
              "price": "0",
              "priceCurrency": "INR",
              "availability": "https://schema.org/InStock",
              "url": window.location.href,
              "validFrom": "2025-11-01T00:00:00+05:30"
            },
            "performer": {
              "@id": `${window.location.origin}/#organization`
            }
          },
          {
            "@type": "FAQPage",
            "@id": `${window.location.origin}/#faqpage`,
            "mainEntity": [
              {
                "@type": "Question",
                "name": "What is Animal Writes?",
                "acceptedAnswer": {
                  "@type": "Answer",
                  "text": "Animal Writes is India's largest citizen-led letter petition movement where thousands of people will walk to their nearest GPO/HPO on November 29, 2025, to post handwritten letters to the Supreme Court of India advocating for humane coexistence with street dogs."
                }
              },
              {
                "@type": "Question",
                "name": "When is the campaign?",
                "acceptedAnswer": {
                  "@type": "Answer",
                  "text": "The national campaign day is November 29, 2025. Citizens across India will walk to their nearest General Post Office (GPO) or Head Post Office (HPO) to post their letters to the Supreme Court."
                }
              },
              {
                "@type": "Question",
                "name": "How can I participate?",
                "acceptedAnswer": {
                  "@type": "Answer",
                  "text": "Write a letter using our templates or your own words, address it to the Supreme Court of India, and post it via Speed Post from your nearest GPO/HPO on November 29, 2025. Share your participation on social media with #SaveIndianStreetDogs and #AnimalWrites."
                }
              },
              {
                "@type": "Question",
                "name": "Is there a fee to participate?",
                "acceptedAnswer": {
                  "@type": "Answer",
                  "text": "Participation in Animal Writes is completely free. You only need to cover the cost of posting your letter via Speed Post at your local GPO/HPO."
                }
              },
              {
                "@type": "Question",
                "name": "What should I write in my letter?",
                "acceptedAnswer": {
                  "@type": "Answer",
                  "text": "You can use our provided letter templates or write your own heartfelt message to the Supreme Court. Express your support for humane coexistence with street dogs and the importance of the ABC (Animal Birth Control) program in India."
                }
              }
            ]
          },
          {
            "@type": "CampaignAction",
            "@id": `${window.location.origin}/#campaign`,
            "name": "Animal Writes Campaign",
            "description": "A nationwide campaign to advocate for animal rights through citizen letters to the Supreme Court of India",
            "agent": {
              "@id": `${window.location.origin}/#organization`
            },
            "actionStatus": "https://schema.org/ActiveActionStatus",
            "startTime": "2025-11-01T00:00:00+05:30",
            "endTime": "2025-11-29T23:59:59+05:30",
            "target": {
              "@type": "EntryPoint",
              "urlTemplate": `${window.location.origin}/#how-to-join`,
              "actionPlatform": [
                "http://schema.org/DesktopWebPlatform",
                "http://schema.org/MobileWebPlatform"
              ]
            }
          }
        ]
      };

      const script = document.createElement('script');
      script.type = 'application/ld+json';
      script.text = JSON.stringify(structuredData);
      document.head.appendChild(script);
    };

    addStructuredData();

    // Additional Apple Touch Icons for better iOS integration
    const addAppleIcons = () => {
      const logoUrl = "https://vedanshu.design/AW/logo";
      const sizes = [57, 60, 72, 76, 114, 120, 144, 152, 180];
      
      sizes.forEach(size => {
        if (!document.querySelector(`link[rel="apple-touch-icon"][sizes="${size}x${size}"]`)) {
          const link = document.createElement('link');
          link.rel = 'apple-touch-icon';
          link.sizes = `${size}x${size}`;
          link.href = logoUrl;
          document.head.appendChild(link);
        }
      });
    };
    
    addAppleIcons();

  }, []);

  return null; // This component doesn't render anything
}