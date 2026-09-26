<?php
// Centralized media + site placeholders. Swap client originals by changing paths here only.
const LOCATION = "Arizona, USA";
const PHONE_PENDING = "[PHONE — PENDING CLIENT CONFIRMATION]";

function media_asset(string $name, string $alt): array
{
    return [
        "src" => "assets/images/" . $name . ".webp",
        "srcSet" =>
            "assets/images/" . $name . "-800.webp 800w, " .
            "assets/images/" . $name . "-1600.webp 1600w, " .
            "assets/images/" . $name . "-2400.webp 2400w",
        "alt" => $alt,
    ];
}

function sa_asset(string $file, string $srcSet, string $alt, string $position = "", bool $soft = false, bool $portrait = false): array
{
    $out = [
        "src" => "assets/studio-a/" . $file,
        "srcSet" => $srcSet,
        "alt" => $alt,
    ];
    if ($position !== "") {
        $out["position"] = $position;
    }
    if ($soft) {
        $out["softOverlay"] = true;
    }
    if ($portrait) {
        $out["portrait"] = true;
    }
    return $out;
}

function st_asset(string $file, string $srcSet, string $alt, string $position = "", bool $soft = false): array
{
    $out = [
        "src" => "assets/soundtunnel/" . $file,
        "srcSet" => $srcSet,
        "alt" => $alt,
    ];
    if ($position !== "") {
        $out["position"] = $position;
    }
    if ($soft) {
        $out["softOverlay"] = true;
    }
    return $out;
}

function media_src($image): string
{
    return is_array($image) ? (string) $image["src"] : (string) $image;
}

function media_srcset($image): string
{
    return is_array($image) ? (string) ($image["srcSet"] ?? "") : "";
}

function media_alt($image, string $fallback = ""): string
{
    return is_array($image) ? (string) ($image["alt"] ?? $fallback) : $fallback;
}

function media_img($image, string $class = "", string $sizes = "100vw", bool $priority = false, string $alt = ""): string
{
    $src = media_src($image);
    $srcset = media_srcset($image);
    $a = media_alt($image, $alt);
    $loading = $priority ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"';
    // Always root-absolute via url() so /spaces/studio-a etc. don't resolve assets relatively.
    $srcAbs = $src !== "" ? url($src) : "";
    $srcsetAbs = "";
    if ($srcset !== "") {
        $parts = [];
        foreach (preg_split('/\s*,\s*/', $srcset) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === "") {
                continue;
            }
            if (preg_match('/^(\S+)(\s+\S+)?$/', $chunk, $m)) {
                $parts[] = url($m[1]) . ($m[2] ?? "");
            }
        }
        $srcsetAbs = implode(", ", $parts);
    }
    $srcsetAttr = $srcsetAbs !== "" ? ' srcset="' . e($srcsetAbs) . '" sizes="' . e($sizes) . '"' : "";
    $classAttr = $class !== "" ? ' class="' . e($class) . '"' : ' class="mw-cover"';
    $pos = is_array($image) ? (string) ($image["position"] ?? "") : "";
    // Inline cover styles so Hostinger cache / missing utilities cannot stretch photos
    $isHero = str_contains($class, "hero-media");
    $style = $isHero
        ? "position:absolute;inset:0;width:100%;height:100%;max-width:none;object-fit:cover;display:block;"
        : "width:100%;height:100%;max-width:none;object-fit:cover;display:block;";
    if ($pos !== "") {
        $style .= "object-position:" . $pos . ";";
    }
    return '<img src="' . e($srcAbs) . '"' . $srcsetAttr . $classAttr . ' style="' . e($style) . '" alt="' . e($a) . '" ' . $loading . ' decoding="async" />';
}

function location_for(string $service = "default"): string
{
    global $SITE;
    return $SITE["locations"][$service] ?? $SITE["location"];
}

$SITE = [
    "name" => "Musicians World",
    "network" => "MusiciansWorld Creative Network",
    "tagline" => "CREATE. CONNECT. LEARN. PERFORM. GROW.",
    "location" => LOCATION,
    "locationLabel" => LOCATION,
    "locations" => [
        "default" => LOCATION,
        "studio-a" => LOCATION,
        "soundtunnel" => LOCATION,
        "dj-school" => LOCATION,
    ],
    "established" => 2018,
    "phone" => PHONE_PENDING,
    "email" => "booking@wavemakerconcepts.com",
    "hours" => "Mon – Sun · 9AM – 2AM",
];

$IMAGES = [
    "hero" => sa_asset(
        "studio-a-room.webp",
        "assets/studio-a/studio-a-room-480.webp 480w, assets/studio-a/studio-a-room-800.webp 800w, assets/studio-a/studio-a-room-1200.webp 1200w",
        "Studio A at Musicians World — drums, guitars, mics, and warm wood walls",
        "center 45%",
        true
    ),
    "studioARoom" => sa_asset(
        "studio-a-room.webp",
        "assets/studio-a/studio-a-room-480.webp 480w, assets/studio-a/studio-a-room-800.webp 800w, assets/studio-a/studio-a-room-1200.webp 1200w",
        "Studio A full room with drums, guitars, mics, and warm wood walls",
        "center 45%",
        true
    ),
    "studioALounge" => sa_asset(
        "studio-a-lounge.webp",
        "assets/studio-a/studio-a-lounge-480.webp 480w, assets/studio-a/studio-a-lounge-800.webp 800w, assets/studio-a/studio-a-lounge-1200.webp 1200w",
        "Studio A lounge corner with red sofa, mic stand, and pink lighting",
        "center 40%",
        true
    ),
    "studioABooth" => sa_asset(
        "studio-a-booth.webp",
        "assets/studio-a/studio-a-booth-480.webp 480w, assets/studio-a/studio-a-booth-800.webp 800w",
        "Studio A vocal booth view with microphones, red sofa, and neon lighting",
        "center 35%",
        true,
        true
    ),
    "studioAKit" => sa_asset(
        "studio-a-kit.webp",
        "assets/studio-a/studio-a-kit-480.webp 480w, assets/studio-a/studio-a-kit-800.webp 800w, assets/studio-a/studio-a-kit-1200.webp 1200w",
        "Studio A Roland electronic drum kit and Yamaha keyboard under blue light",
        "center 50%",
        true
    ),
    "studioASession" => sa_asset(
        "studio-a-session.webp",
        "assets/studio-a/studio-a-session-480.webp 480w, assets/studio-a/studio-a-session-800.webp 800w, assets/studio-a/studio-a-session-1200.webp 1200w",
        "Studio A session floor with drums, mics, seating, and red-blue wash lighting",
        "center 45%",
        true
    ),
    "studioARehearsal" => null, // filled below
    "djPerforming" => media_asset("dj-performing", "DJ performing on decks under neon stage lighting"),
    "djSchool" => media_asset("dj-school-students", "DJ with headphones performing under blue club lighting"),
    "soundtunnelEvent" => media_asset("soundtunnel-event", "Crowd and performers at a SoundTunnel-style live event"),
    "contentCreation" => media_asset("content-creation", "Videographer working with cinema camera and studio lighting at Musicians World"),
    "creatorsNetworking" => media_asset("creators-networking", "Creators talking and collaborating at a night event"),
    "buildingExterior" => media_asset("building-exterior", "Night-time building exterior and street approach"),
    "soundtunnelDj" => st_asset(
        "soundtunnel-dj-lasers.webp",
        "assets/soundtunnel/soundtunnel-dj-lasers-480.webp 480w, assets/soundtunnel/soundtunnel-dj-lasers-768.webp 768w, assets/soundtunnel/soundtunnel-dj-lasers-1023.webp 1023w",
        "DJ performing at SoundTunnel with laser lighting and branded LED wall",
        "center 40%",
        true
    ),
    "soundtunnelVenue" => st_asset(
        "soundtunnel-venue-graded.webp",
        "assets/soundtunnel/soundtunnel-venue-480.webp 480w, assets/soundtunnel/soundtunnel-venue-768.webp 768w, assets/soundtunnel/soundtunnel-venue-1200.webp 1200w, assets/soundtunnel/soundtunnel-venue-1920.webp 1920w",
        "SoundTunnel venue hall with DJ booth and speakers under stage-graded lighting",
        "center 65%"
    ),
];
$IMAGES["studioARehearsal"] = $IMAGES["studioARoom"];

$SOUNDTUNNEL = [
    "hero" => "assets/soundtunnel/soundtunnel-dj-lasers.webp",
    "venue" => "assets/soundtunnel/soundtunnel-venue-graded.webp",
    "video" => "assets/soundtunnel/soundtunnel-loop.mp4",
    "videoWebm" => "assets/soundtunnel/soundtunnel-loop.webm",
    "poster" => "assets/soundtunnel/soundtunnel-poster.jpg",
];

$MEDIA = [
    "logo" => "assets/brand/logo.png",
    "hero" => $IMAGES["hero"],
    "studioA" => [
        $IMAGES["studioARoom"],
        $IMAGES["studioAKit"],
        $IMAGES["studioALounge"],
        $IMAGES["studioASession"],
        $IMAGES["studioABooth"],
    ],
    /** Gallery stills — video slot is composed on soundtunnel.php, not duplicated here. */
    "soundtunnel" => [$IMAGES["soundtunnelVenue"], $IMAGES["soundtunnelDj"]],
    "soundtunnelDj" => $IMAGES["soundtunnelDj"],
    "soundtunnelVenue" => $IMAGES["soundtunnelVenue"],
    "dj" => $IMAGES["djSchool"],
    "djPerform" => $IMAGES["soundtunnelDj"],
    "events" => $IMAGES["soundtunnelDj"],
    "membership" => $IMAGES["creatorsNetworking"],
    "create" => $IMAGES["contentCreation"],
    "building" => $IMAGES["buildingExterior"],
    "about" => $IMAGES["studioARoom"],
    "contact" => $IMAGES["creatorsNetworking"],
];

$STORIES = [
    [
        "kicker" => "Studio A",
        "title" => "Musicians in the room.",
        "line" => "",
        "body" => "Rehearsal, practice, and sessions that actually start. Studio A is built for artists using the kit, the PA, and the mics — not an empty lockout on a listing photo.",
        "image" => $IMAGES["studioARoom"],
        "href" => "/spaces/studio-a",
        "cta" => "View Studio A",
        "tone" => "photo",
        "flip" => false,
    ],
    [
        "kicker" => "DJs",
        "title" => "Performers on the decks.",
        "line" => "",
        "body" => "DJ nights, live sets, and a booth that is meant to be played. Energy in the room — equipment, atmosphere, and a performance environment.",
        "image" => $IMAGES["soundtunnelDj"],
        "href" => "/soundtunnel",
        "cta" => "Explore SoundTunnel",
        "tone" => "dark",
        "flip" => true,
        "portrait" => true,
    ],
    [
        "kicker" => "DJ School",
        "title" => "Learn in a booth.",
        "line" => "Learn → Practice → Improve → Perform",
        "body" => "Fundamentals, beginner classes, and private lessons in a real playing environment. Registration is a request to the desk — tuition is confirmed when it is current.",
        "image" => $IMAGES["djSchool"],
        "href" => "/dj-school",
        "cta" => "Explore DJ School",
        "tone" => "dark",
        "flip" => false,
    ],
    [
        "kicker" => "SoundTunnel",
        "title" => "The night has a crowd.",
        "line" => "",
        "body" => "Events, live performances, and people in the room. SoundTunnel is a performance environment — audio, lighting, LED wall, DJ capabilities — quoted from the desk.",
        "image" => $IMAGES["soundtunnelDj"],
        "href" => "/soundtunnel",
        "cta" => "See SoundTunnel",
        "tone" => "dark",
        "flip" => false,
        "portrait" => true,
    ],
    [
        "kicker" => "Content",
        "title" => "Cameras in the house.",
        "line" => "",
        "body" => "Photographers, videographers, and creators use the same ecosystem. Lighting, cameras, and production sit next to the rooms — not in a separate brochure.",
        "image" => $IMAGES["contentCreation"],
        "href" => "/about",
        "cta" => "About the house",
        "tone" => "dark",
        "flip" => true,
    ],
    [
        "kicker" => "Community",
        "title" => "Creators in the same room.",
        "line" => "",
        "body" => "Musicians World is a creative network, not only a calendar of lockouts. People meet, collaborate, and stay in the loop after the session ends.",
        "image" => $IMAGES["creatorsNetworking"],
        "href" => "/membership",
        "cta" => "Join the network",
        "tone" => "dark",
        "flip" => false,
    ],
    [
        "kicker" => "Location",
        "title" => "The house has a door.",
        "line" => "",
        "body" => "A physical creative house in Arizona, USA. Studio A, SoundTunnel, DJ School, and membership under one roof.",
        "image" => $IMAGES["buildingExterior"],
        "href" => "/contact",
        "cta" => "Talk to the desk",
        "tone" => "photo",
        "flip" => false,
    ],
];

$STUDIO_A_DURATIONS = [2, 3, 4, 6, 8];
$STUDIO_A_PRICES = [2 => 80, 3 => 105, 4 => 140, 6 => 200, 8 => 260];
$STUDIO_A_EQUIPMENT = ["Full drum kit", "Professional PA system", "Up to 4 microphones"];
$STUDIO_A_ADDONS = [];

// PLACEHOLDER — pending client approval
// Phase 2 tiers (Creative Network, Pro) are fully built below but stay hidden
// on the live site until the desk flips this flag.
$show_phase_2 = false;

$MEMBERSHIPS = [
    [
        "id" => "community",
        "phase" => "phase1",
        "pill" => "Phase 1 · Live now",
        "icon" => "users",
        "name" => "Free Community",
        "subtitle" => "Get connected",
        "priceValue" => "\$0",
        "pricePeriod" => "/ month",
        "priceNote" => "No credit card required",
        "cta" => "Join free",
        "waitlist" => false,
        "featured" => false,
        "includesLabel" => "",
        "headline" => "Join the MusiciansWorld Creative Network and stay in the loop. A great way to get started, meet the community and be the first to know.",
        "disclaimer" => "Free Community Membership does not include recurring studio discounts, included studio hours or paid member benefits.",
        "benefits" => [
            "MWCN news & announcements",
            "Upcoming event notifications",
            "Open House & special event updates",
            "Community opportunities",
            "Selected creative calls & opportunities",
            "Workshop & class announcements",
            "SoundTunnel event updates",
            "Access to selected member communications",
            "Be among the first to hear what's happening across MWCN",
        ],
    ],
    [
        "id" => "founding",
        "phase" => "phase1",
        "pill" => "Phase 1 · Live now",
        "icon" => "crown",
        "name" => "Founding Member",
        "subtitle" => "Be part of the beginning",
        "priceValue" => "\$150",
        "pricePeriod" => "/ month",
        "priceNote" => "Billed monthly via Stripe",
        "cta" => "Become a Founding Member",
        "waitlist" => false,
        "featured" => true,
        "includesLabel" => "Includes everything in Free, plus:",
        "headline" => "Create more. Access more. Your support helps build the foundation of MWCN while giving you early access to exclusive opportunities.",
        "disclaimer" => "Founding Member is limited to 50 seats. \$150 / month, cancel anytime from your Stripe customer portal or by contacting the desk.",
        "benefits" => [
            "One 2-Hour Studio A Session Included Each Month",
            "Special member pricing on DJ School classes & private lessons",
            "Early access to selected events, workshops & showcases",
            "Selected SoundTunnel event perks & member offers",
            "Creative networking & collaboration opportunities",
            "Early consideration for selected showcases & creative opportunities",
            "Eligibility for the MWCN Creative Directory",
            "Founding Member-only opportunities & special offers",
        ],
    ],
    [
        "id" => "creative_network",
        "phase" => "phase2",
        "pill" => "Phase 2 · Coming soon",
        "icon" => "cap",
        "name" => "Creative Network Membership",
        "subtitle" => "Develop your craft. Expand your network.",
        "priceValue" => "TBD",
        "pricePeriod" => "",
        "priceNote" => "Coming in Phase 2",
        "cta" => "Join waitlist",
        "waitlist" => true,
        "featured" => false,
        "includesLabel" => "Includes everything in Founding, plus:",
        "headline" => "For active creators who want more education, resources and opportunities within the MWCN network.",
        "disclaimer" => "Creative Network Membership pricing and launch date are not final.",
        "benefits" => [
            "One DJ School group class or MWCN creative workshop included each month",
            "Expanded Studio A hours (at a discounted member rate)",
            "Additional discounts on DJ School classes & private lessons",
            "Priority access to workshops, showcases & selected live events",
            "Enhanced Creative Directory profile",
            "Member-only resources, templates & tools",
            "Opportunities to be featured across MWCN platforms",
            "Invitations to special networking events",
            "Early access to partner opportunities",
        ],
    ],
    [
        "id" => "pro",
        "phase" => "phase2",
        "pill" => "Phase 2 · Coming soon",
        "icon" => "trending",
        "name" => "Pro Membership",
        "subtitle" => "Take it further",
        "priceValue" => "TBD",
        "pricePeriod" => "",
        "priceNote" => "Coming in Phase 2",
        "cta" => "Join waitlist",
        "waitlist" => true,
        "featured" => false,
        "includesLabel" => "Includes everything in Creative Network, plus:",
        "headline" => "For serious creators and industry professionals ready for advanced support, visibility and growth.",
        "disclaimer" => "Pro Membership pricing and launch date are not final.",
        "benefits" => [
            "Two DJ School group classes or MWCN creative workshops included each month",
            "Highest member discount on private DJ lessons",
            "Increased Studio A hours (at a discounted member rate)",
            "Priority DJ School registration",
            "Priority consideration for showcases, live events and placement opportunities",
            "Featured Creative Directory listing (with priority placement)",
            "Access to industry insights, panels and mentor sessions",
            "Early access to new spaces, services and pilot programs",
            "Dedicated support for collaborative projects and career growth",
        ],
    ],
];

$DJ_PROGRAMS = [
    [
        "slug" => "dj-fundamentals",
        "layout" => "course",
        "name" => "DJ Fundamentals",
        "level" => "Beginner",
        "tagline" => "Beginner · Structured Course",
        "cardBody" => "Start from zero and build the skills to perform a complete DJ set.",
        "ctaLabel" => "View course",
        "forWho" => "Go from beginner to performing your first complete DJ set. No previous experience or equipment required.",
        "stats" => [
            ["label" => "Length", "value" => "8 Weeks"],
            ["label" => "Commitment", "value" => "2 Hours / Week"],
            ["label" => "Class size", "value" => "Small Groups"],
            ["label" => "Gear", "value" => "Equipment Provided"],
        ],
        "learn" => [
            "DJ equipment & signal flow",
            "Beatmatching & tempo control",
            "Phrasing & track structure",
            "EQ mixing & transitions",
            "Cueing, loops & effects",
            "Serato / Rekordbox fundamentals",
            "Music library organization",
            "Set building & song selection",
            "Performance fundamentals",
            "Final recorded DJ set",
        ],
        "testimonial" => "It always seems impossible until it’s done.",
        "length" => "8 weeks, 2 hours/week",
        "classSize" => "Small group",
        "equipment" => "House DJ equipment provided",
        "need" => "Headphones recommended.",
    ],
    [
        "slug" => "dj-performance-lab",
        "layout" => "course",
        "name" => "DJ Performance Lab",
        "level" => "Intermediate",
        "tagline" => "Intermediate · Hands-On Training",
        "cardBody" => "Improve your mixing, transitions, programming, crowd reading and live performance skills.",
        "ctaLabel" => "View program",
        "forWho" => "Take your mixing from practice room to performance-ready. Built for DJs who already know the basics and want real stage time.",
        "stats" => [
            ["label" => "Length", "value" => "6 Weeks"],
            ["label" => "Commitment", "value" => "2 Hours / Week"],
            ["label" => "Class size", "value" => "Small Groups"],
            ["label" => "Gear", "value" => "Equipment Provided"],
        ],
        "learn" => [
            "Advanced blending & transitions",
            "Reading and building a crowd",
            "Set programming & pacing",
            "Live performance technique",
            "Multi-genre mixing",
            "Using effects & loops live",
            "Backup planning & gear troubleshooting",
            "Recording and reviewing your sets",
        ],
        "testimonial" => "Practice like it’s the crowd. Perform like it’s practice.",
        "length" => "6 weeks, 2 hours/week",
        "classSize" => "Small group",
        "equipment" => "House DJ equipment provided",
        "need" => "Headphones recommended.",
    ],
    [
        "slug" => "private-dj-lessons",
        "layout" => "private",
        "name" => "Private DJ Lessons",
        "level" => "All levels",
        "tagline" => "All Levels · 1-on-1",
        "cardBody" => "Personalized coaching with a professional DJ instructor.",
        "ctaLabel" => "View lesson packages",
        "forWho" => "Whether you’re touching DJ equipment for the first time or preparing for your next performance, private lessons are built around your goals, equipment, and skill level.",
        "features" => [
            ["icon" => "target", "label" => "Personalized Coaching"],
            ["icon" => "bars", "label" => "All Skill Levels"],
            ["icon" => "gear", "label" => "Work On Your Own Equipment"],
            ["icon" => "calendar", "label" => "Flexible Scheduling"],
        ],
        "length" => "Scheduled privately",
        "classSize" => "1-on-1",
        "equipment" => "House DJ equipment provided. Bring a controller if you prefer.",
        "need" => "Headphones. Controller optional.",
        "learn" => ["A plan built around your music", "Technique on the gear you’ll actually play", "Set construction and performance notes"],
    ],
];

$EVENT_CATEGORIES = ["DJ Events", "Live Performances", "Workshops", "Artist Showcases", "Community Events", "Open Houses"];
$SOUNDTUNNEL_USES = ["DJ Events", "Live Performances", "Private Events", "Artist Showcases", "Workshops", "Creative Events", "Community Events"];
$SOUNDTUNNEL_FEATURES = ["Professional Audio", "Lighting", "LED Video Wall", "DJ Capabilities", "Performance Environment"];

$RESOURCES = [
    "studio-a" => [
        "id" => "studio-a",
        "name" => "Studio A",
        "image" => $MEDIA["studioA"][0],
    ],
    "soundtunnel" => [
        "id" => "soundtunnel",
        "name" => "SoundTunnel",
        "image" => $IMAGES["soundtunnelDj"],
    ],
];

foreach (['SITE','IMAGES','SOUNDTUNNEL','MEDIA','STORIES','STUDIO_A_DURATIONS','STUDIO_A_PRICES','STUDIO_A_EQUIPMENT','STUDIO_A_ADDONS','MEMBERSHIPS','DJ_PROGRAMS','EVENT_CATEGORIES','SOUNDTUNNEL_USES','SOUNDTUNNEL_FEATURES','RESOURCES','show_phase_2'] as $mwcnKey) { $GLOBALS[$mwcnKey] = $$mwcnKey; }
