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
        { href: 'https://vedanshu.design', crossorigin: true },
        { href: 'https://images.unsplash.com', crossorigin: true }
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

      // Preload critical images with high priority
      const preloadImage = (href: string, fetchpriority = 'auto') => {
        if (!document.querySelector(`link[rel="preload"][href="${href}"]`)) {
          const link = document.createElement('link');
          link.rel = 'preload';
          link.as = 'image';
          link.href = href;
          if (fetchpriority !== 'auto') {
            link.setAttribute('fetchpriority', fetchpriority);
          }
          link.crossOrigin = 'anonymous';
          // Insert at the beginning of head for highest priority
          document.head.insertBefore(link, document.head.firstChild);
        }
      };

      // Preload hero image with WebP for better performance - optimized size and quality for fastest LCP
      // Using fetchpriority="high" and inserting first for maximum priority - reduced to 280px w/ q=30 for mobile sub-2.5s LCP
      // Preload mobile version (smallest) since Lighthouse tests on mobile
      preloadImage('https://images.unsplash.com/photo-1732812088418-f2fe47b11448?crop=entropy&cs=tinysrgb&fit=max&fm=webp&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxpbmRpYW4lMjBzdHJlZXQlMjBkb2clMjBjb21wYXNzaW9ufGVufDF8fHx8MTc2MzIxOTQyMHww&ixlib=rb-4.1.0&q=30&w=280&auto=format&dpr=1', 'high');
      // Preload logo
      preloadImage('https://vedanshu.design/AW/logo', 'high');
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
    setMetaTag("description", "Join India's largest letter petition campaign on November 29, 2025. Thousands will walk to post offices across India to mail handwritten letters to the Supreme Court, urging reconsideration of the stray dog removal order. Write your letter for compassion and coexistence.");
    setMetaTag("keywords", "Animal Writes, letter petition, street dogs India, animal welfare, Supreme Court petition, stray dogs, GPO India, animal rights campaign, November 29 2025, handwritten letters, compassion movement, coexistence");
    setMetaTag("author", "Animal Writes Campaign");
    setMetaTag("robots", "index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1");
    setMetaTag("viewport", "width=device-width, initial-scale=1.0, viewport-fit=cover");
    setMetaTag("theme-color", "#F59E0B");
    
    // Enhanced SEO meta tags
    setMetaTag("googlebot", "index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1");
    setMetaTag("bingbot", "index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1");
    setMetaTag("coverage", "Worldwide");
    setMetaTag("distribution", "Global");
    setMetaTag("rating", "General");
    setMetaTag("revisit-after", "7 days");
    setMetaTag("target", "all");
    setMetaTag("HandheldFriendly", "True");
    setMetaTag("MobileOptimized", "width");
    setMetaTag("apple-mobile-web-app-capable", "yes");
    setMetaTag("apple-mobile-web-app-status-bar-style", "black-translucent");
    setMetaTag("apple-mobile-web-app-title", "Animal Writes");
    setMetaTag("application-name", "Animal Writes");
    setMetaTag("msapplication-TileColor", "#F59E0B");
    setMetaTag("msapplication-config", "none");
    
    // Geo tags
    setMetaTag("geo.region", "IN");
    setMetaTag("geo.placename", "India");
    setMetaTag("geo.position", "20.5937;78.9629");
    setMetaTag("ICBM", "20.5937, 78.9629");
    
    // Language and locale
    setMetaTag("language", "English");
    setMetaTag("content-language", "en");

    // Open Graph (Facebook, LinkedIn)
    setMetaTag("og:type", "website", true);
    setMetaTag("og:url", "https://animalwrites.in/", true);
    setMetaTag("og:site_name", "Animal Writes - India's Largest Letter Petition Campaign", true);
    setMetaTag("og:title", "Animal Writes - Join India's Largest Letter Petition for Street Dogs", true);
    setMetaTag("og:description", "On November 29, 2025, thousands across India will walk to their nearest post office to mail handwritten letters to the Supreme Court. Join the movement for compassion and coexistence.", true);
    setMetaTag("og:image", "https://vedanshu.design/AW/og-image", true);
    setMetaTag("og:image:secure_url", "https://vedanshu.design/AW/og-image", true);
    setMetaTag("og:image:type", "image/png", true);
    setMetaTag("og:image:width", "1200", true);
    setMetaTag("og:image:height", "630", true);
    setMetaTag("og:image:alt", "Animal Writes Campaign - Write letters for street dogs", true);
    setMetaTag("og:locale", "en_IN", true);
    setMetaTag("og:locale:alternate", "en_US", true);

    // Twitter Card
    setMetaTag("twitter:card", "summary_large_image");
    setMetaTag("twitter:url", "https://animalwrites.in/");
    setMetaTag("twitter:title", "Animal Writes - Join India's Largest Letter Petition for Street Dogs");
    setMetaTag("twitter:description", "On November 29, 2025, thousands across India will walk to their nearest post office to mail handwritten letters to the Supreme Court. Join the movement for compassion.");
    setMetaTag("twitter:image", "https://vedanshu.design/AW/og-image");
    setMetaTag("twitter:image:alt", "Animal Writes Campaign - Write letters for street dogs");
    setMetaTag("twitter:creator", "@AnimalWrites");
    setMetaTag("twitter:site", "@AnimalWrites");
    
    // Additional Twitter tags
    setMetaTag("twitter:label1", "Event Date");
    setMetaTag("twitter:data1", "November 29, 2025");
    setMetaTag("twitter:label2", "Location");
    setMetaTag("twitter:data2", "All GPOs & HPOs across India");

    // Structured data - Event
    const eventSchema = {
      "@context": "https://schema.org",
      "@type": "Event",
      "name": "Animal Writes - National Letter Petition Campaign",
      "description": "India's largest letter petition movement where thousands of citizens walk to their nearest GPO to post handwritten letters to the Supreme Court urging reconsideration of the stray dog removal order.",
      "startDate": "2025-11-29T09:00:00+05:30",
      "endDate": "2025-11-29T18:00:00+05:30",
      "eventStatus": "https://schema.org/EventScheduled",
      "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
      "location": {
        "@type": "Place",
        "name": "General Post Offices and Head Post Offices across India",
        "address": {
          "@type": "PostalAddress",
          "addressCountry": "IN",
          "addressRegion": "Multiple States"
        }
      },
      "image": "https://vedanshu.design/AW/og-image",
      "organizer": {
        "@type": "Organization",
        "name": "Animal Writes Campaign",
        "url": "https://animalwrites.in/"
      },
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "INR",
        "availability": "https://schema.org/InStock",
        "url": "https://animalwrites.in/",
        "validFrom": "2025-10-01T00:00:00+05:30"
      }
    };

    // Structured data - Organization
    const organizationSchema = {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "Animal Writes",
      "alternateName": "Animal Writes Campaign",
      "url": "https://animalwrites.in/",
      "logo": "https://vedanshu.design/AW/logo",
      "description": "India's largest letter petition movement for street dog welfare and coexistence",
      "sameAs": [
        "https://twitter.com/AnimalWrites",
        "https://facebook.com/AnimalWrites",
        "https://instagram.com/AnimalWrites"
      ],
      "contactPoint": {
        "@type": "ContactPoint",
        "contactType": "Customer Service",
        "availableLanguage": ["English", "Hindi"]
      }
    };

    // Structured data - Website
    const websiteSchema = {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "Animal Writes",
      "url": "https://animalwrites.in/",
      "description": "India's largest letter petition campaign for street dog welfare",
      "publisher": {
        "@type": "Organization",
        "name": "Animal Writes Campaign"
      },
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://animalwrites.in/?s={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    };

    // Structured data - Cause
    const causeSchema = {
      "@context": "https://schema.org",
      "@type": "SocialEvent",
      "name": "Animal Writes Letter Petition Campaign",
      "description": "A peaceful civic movement where citizens across India write and post letters to the Supreme Court advocating for compassionate treatment of street dogs",
      "url": "https://animalwrites.in/",
      "startDate": "2025-11-29",
      "image": "https://vedanshu.design/AW/og-image"
    };

    // Structured data - BreadcrumbList
    const breadcrumbSchema = {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "Home",
          "item": "https://animalwrites.in/"
        },
        {
          "@type": "ListItem",
          "position": 2,
          "name": "About Campaign",
          "item": "https://animalwrites.in/#about-movement"
        },
        {
          "@type": "ListItem",
          "position": 3,
          "name": "How to Join",
          "item": "https://animalwrites.in/#how-to-join"
        },
        {
          "@type": "ListItem",
          "position": 4,
          "name": "Letter Templates",
          "item": "https://animalwrites.in/#letter-templates"
        }
      ]
    };

    // Structured data - FAQPage
    const faqSchema = {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "What is Animal Writes?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Animal Writes is India's largest letter petition campaign where thousands of citizens walk to their nearest GPO on November 29, 2025, to post handwritten letters to the Supreme Court of India urging reconsideration of the stray dog removal/relocation order."
          }
        },
        {
          "@type": "Question",
          "name": "When is the Animal Writes campaign happening?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "The campaign is happening on November 29, 2025. Participants will walk to their local General Post Offices (GPOs) and Head Post Offices (HPOs) across India to mail their letters."
          }
        },
        {
          "@type": "Question",
          "name": "How can I participate in Animal Writes?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "You can participate by: 1) Writing a handwritten letter using our templates, 2) Walking to your nearest GPO or HPO on November 29, 2025, 3) Posting your letter to the Supreme Court of India, and 4) Uploading your postal receipt to show your participation."
          }
        }
      ]
    };

    // Structured data - Action
    const actionSchema = {
      "@context": "https://schema.org",
      "@type": "Action",
      "name": "Join Animal Writes Campaign",
      "description": "Participate in India's largest letter petition for street dog welfare",
      "url": "https://animalwrites.in/#how-to-join",
      "target": {
        "@type": "EntryPoint",
        "urlTemplate": "https://animalwrites.in/#letter-templates",
        "actionPlatform": [
          "http://schema.org/DesktopWebPlatform",
          "http://schema.org/MobileWebPlatform"
        ]
      }
    };

    // Insert all structured data
    const schemas = [eventSchema, organizationSchema, websiteSchema, causeSchema, breadcrumbSchema, faqSchema, actionSchema];
    
    schemas.forEach((schema, index) => {
      const scriptId = `structured-data-${index}`;
      let script = document.getElementById(scriptId) as HTMLScriptElement;
      
      if (!script) {
        script = document.createElement('script');
        script.id = scriptId;
        script.type = 'application/ld+json';
        document.head.appendChild(script);
      }
      
      script.textContent = JSON.stringify(schema);
    });

    // Canonical URL
    let canonical = document.querySelector("link[rel='canonical']") as HTMLLinkElement;
    if (!canonical) {
      canonical = document.createElement("link");
      canonical.rel = "canonical";
      document.head.appendChild(canonical);
    }
    canonical.href = "https://animalwrites.in/";

    // Alternate language versions
    const alternateLangs = [
      { lang: "en-IN", href: "https://animalwrites.in/" },
      { lang: "en", href: "https://animalwrites.in/" },
      { lang: "x-default", href: "https://animalwrites.in/" }
    ];

    alternateLangs.forEach(({ lang, href }) => {
      const linkId = `alternate-${lang}`;
      let link = document.getElementById(linkId) as HTMLLinkElement;
      
      if (!link) {
        link = document.createElement("link");
        link.id = linkId;
        link.rel = "alternate";
        link.hrefLang = lang;
        document.head.appendChild(link);
      }
      
      link.href = href;
    });

  }, []);

  return null;
}
