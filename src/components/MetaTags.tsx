import { useEffect } from "react";

export function MetaTags() {
  useEffect(() => {
    // Set document title
    document.title = "Animal Writes - India's Largest Letter Petition Campaign - 2.5 Lakh Letters Delivered | Join Phase 2.0";
    
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

    // Set favicon - Using SVG data URL for reliability
    const setFavicon = () => {
      // Create an SVG favicon with the Animal Writes pen and paw logo
      const faviconSVG = `
        <svg width="64" height="64" viewBox="0 0 570 641" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="570" height="641" fill="white"/>
          <g transform="translate(50, 80) scale(0.8)">
            <path d="M479.77 306.729C499.33 304.089 510.39 317.909 514.72 335.269C522.51 366.529 502.69 408.739 469.07 415.019C463.94 415.979 462.22 415.209 457.67 413.329C416.89 396.449 441.6 311.889 479.78 306.739L479.77 306.729Z" fill="black"/>
            <path d="M344.59 459.039C342.6 461.249 339.3 464.229 336.72 465.669C335.22 466.499 327.45 469.999 326.23 470.269C324.62 470.609 316.25 468.429 314.23 467.749C280.92 456.569 268.04 395.719 300.3 376.259C311.74 369.359 321.33 371.569 331.49 379.489C352.76 396.089 363.99 437.509 344.59 459.039Z" fill="black"/>
            <path d="M539.78 392.659C551.57 391.219 564.17 402.639 567.74 413.239C576.56 439.409 557.56 474.679 531.81 483.759C504.95 493.229 488.43 460.239 495.28 437.229C501.02 417.969 518.44 395.249 539.78 392.649V392.659Z" fill="black"/>
            <path d="M544.88 2.58878C545.3 3.23878 545.22 5.49878 544.78 6.26878C544.29 7.12878 535.15 13.7088 533.46 15.4288C488.78 61.1288 452.84 127.769 415.82 180.789C345.81 281.039 255 365.709 152.01 431.469C151.78 429.739 152.14 428.339 152.78 426.749C156.3 417.939 169.03 399.559 174.77 390.739C232.86 301.489 306.73 217.299 378.35 138.819C379.43 137.629 383.44 136.509 381.51 134.479C373.84 139.959 367.93 147.599 361.02 153.989C298.2 212.099 241.78 277.839 190.61 346.579C149.85 401.339 98.02 480.849 91 549.989C90.74 552.559 92.13 557.239 90.96 558.849C89.52 560.829 85.91 561.149 83.67 562.649C58.94 579.179 41.65 605.019 21 625.979C22.78 627.119 26.31 625.829 28.03 624.999C30.99 623.569 32.46 620.909 37.02 618.989C74.2 603.369 136.38 596.119 176.49 598.479C254.48 603.069 363.72 639.349 436.98 612.949C443.49 610.609 458.19 601.309 462.56 600.529C463.3 600.399 464.93 600.039 464.99 600.959C458.92 609.039 451.13 615.399 442.29 620.259C386.15 651.139 297.68 631.419 236.48 624.479C179.53 618.009 130.76 613.409 73.81 624.779C54.07 628.719 31.24 637.809 12.41 640.379C10.62 640.619 8.77999 640.459 6.98999 640.469V634.469L0 636.469L25.13 527.109C47.86 504.029 70.44 480.559 88.63 453.609C120.74 406.049 136.93 351.239 167.19 302.169C208.49 235.219 268.47 169.459 325.98 115.959C377.68 67.8788 461.3 -6.67122 536.5 0.478782C537.83 0.608782 544.57 2.10878 544.88 2.58878ZM49.19 559.649C50.48 558.839 53.36 559.929 56.53 557.489C69.86 547.199 48.27 532.259 46.05 550.059C45.15 557.269 47.45 553.309 43.81 560.779C37.78 573.159 30.84 585.009 25.02 597.479C27.97 594.529 30.51 590.329 32.83 586.799C35.33 582.989 47.85 560.509 49.2 559.669L49.19 559.649Z" fill="black"/>
            <path d="M350.59 478.049C366.72 463.669 386.84 437.809 405.33 428.289C445.91 407.399 469.79 458.759 488.58 484.409C502.57 503.519 523.75 517.849 518.57 544.529C514.31 566.499 492.45 580.649 470.62 578.369C444.72 575.669 429.92 555.169 401.95 561.909C382.93 566.499 373.71 575.669 351.69 573.019C323.83 569.669 302.76 542.269 314.84 514.799C322.53 497.309 337.35 489.849 350.59 478.049Z" fill="black"/>
            <path d="M385.77 298.689C394.87 297.409 406.05 304.499 411.45 311.539C429.55 335.159 433.88 386.139 406.33 404.289C376.6 423.879 356.36 386.679 352.29 361.679C348.4 337.789 357.34 302.679 385.77 298.699V298.689Z" fill="black"/>
          </g>
        </svg>
      `;
      
      const faviconDataUrl = `data:image/svg+xml,${encodeURIComponent(faviconSVG)}`;
      
      // Standard favicon
      let link = document.querySelector("link[rel*='icon']") as HTMLLinkElement;
      if (!link) {
        link = document.createElement("link");
        link.rel = "icon";
        document.head.appendChild(link);
      }
      link.href = faviconDataUrl;
      
      // Shortcut icon (for older browsers)
      let shortcutIcon = document.querySelector("link[rel='shortcut icon']") as HTMLLinkElement;
      if (!shortcutIcon) {
        shortcutIcon = document.createElement("link");
        shortcutIcon.rel = "shortcut icon";
        document.head.appendChild(shortcutIcon);
      }
      shortcutIcon.href = faviconDataUrl;
      
      // Icon with type specified
      let typedIcon = document.querySelector("link[rel='icon'][type]") as HTMLLinkElement;
      if (!typedIcon) {
        typedIcon = document.createElement("link");
        typedIcon.rel = "icon";
        typedIcon.type = "image/svg+xml";
        document.head.appendChild(typedIcon);
      }
      typedIcon.href = faviconDataUrl;
      
      // Apple touch icon (also uses the logo)
      let appleTouchIcon = document.querySelector("link[rel='apple-touch-icon']:not([sizes])") as HTMLLinkElement;
      if (!appleTouchIcon) {
        appleTouchIcon = document.createElement("link");
        appleTouchIcon.rel = "apple-touch-icon";
        document.head.appendChild(appleTouchIcon);
      }
      appleTouchIcon.href = faviconDataUrl;
    };
    
    setFavicon();

    // Resource Hints for Performance
    const addResourceHints = () => {
      // DNS Prefetch for external domains
      const dnsPrefetchDomains = [
        'https://www.googletagmanager.com',
        'https://www.google-analytics.com',
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
      // Using fetchpriority="high" and inserting first for maximum priority - 400px w/ q=40 for mobile balance
      // Preload mobile version (smallest) since Lighthouse tests on mobile - targets ~18-22KB file size for quality
      preloadImage('https://images.unsplash.com/photo-1732812088418-f2fe47b11448?crop=entropy&cs=tinysrgb&fit=max&fm=webp&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxpbmRpYW4lMjBzdHJlZXQlMjBkb2clMjBjb21wYXNzaW9ufGVufDF8fHx8MTc2MzIxOTQyMHww&ixlib=rb-4.1.0&q=40&w=400&auto=format&dpr=1', 'high');
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
    setMetaTag("description", "Animal Writes - India's largest letter petition campaign achieved historic success! Over 2.5 lakh handwritten letters delivered to Supreme Court on November 29, 2025. Join Phase 2.0: Pashu Bachao Vote Badhao to make animal welfare a democratic priority across India.");
    setMetaTag("keywords", "Animal Writes, letter petition, street dogs India, animal welfare, Supreme Court petition, stray dogs, GPO India, animal rights campaign, November 29 2025, handwritten letters, compassion movement, coexistence");
    setMetaTag("author", "Animal Writes Campaign");
    setMetaTag("robots", "index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1");
    setMetaTag("viewport", "width=device-width, initial-scale=1.0, viewport-fit=cover");
    setMetaTag("theme-color", "rgba(255, 165, 0, 1.00)"); // Using var(--accent) color value
    
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
    setMetaTag("msapplication-TileColor", "rgba(255, 165, 0, 1.00)"); // Using var(--accent) color value
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