<?php

/**
 * One-off downloader to fetch all hotel/room photos to local git-tracked folder.
 * Usage: php database/seeders/hotel-photos/download.php
 *
 * Output structure:
 *   database/seeders/hotel-photos/{hotel-slug}/featured.{ext}
 *   database/seeders/hotel-photos/{hotel-slug}/gallery/{n}.{ext}
 *   database/seeders/hotel-photos/{hotel-slug}/rooms/{room-slug}/{n}.{ext}
 */
$root = __DIR__;

$photos = [
    'fairmont-jakarta' => [
        'featured' => 'https://www.ahstatic.com/photos/a5g1_ho_00_p_2048x1536.jpg',
        'gallery' => [
            'https://www.ahstatic.com/photos/a5g1_ho_01_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/a5g1_ho_02_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/a5g1_ho_03_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/a5g1_bab001_00_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/a5g1_sw_00_p_2048x1536.jpg',
        ],
        'rooms' => [
            'fairmont-room' => [
                'https://www.ahstatic.com/photos/a5g1_rokgaz_00_p_2048x1536.jpg',
                'https://www.ahstatic.com/photos/a5g1_rokgbz_00_p_2048x1536.jpg',
            ],
            'signature-room' => [
                'https://www.ahstatic.com/photos/a5g1_rosba_00_p_2048x1536.jpg',
                'https://www.ahstatic.com/photos/a5g1_roske_00_p_2048x1536.jpg',
            ],
            'fairmont-suite' => [
                'https://www.ahstatic.com/photos/a5g1_roslb_00_p_2048x1536.jpg',
                'https://www.ahstatic.com/photos/a5g1_roslc_00_p_2048x1536.jpg',
            ],
        ],
    ],

    'hotel-mulia-senayan' => [
        'featured' => 'https://cdn.prod.website-files.com/6624ff6a5db57a668993dd4c/66a08a4242de5b6f0fb9d099_Hotel%20Mulia%20Senayan%2C%20Jakarta%20-%20Exterior2.webp',
        'gallery' => [
            'https://cdn.prod.website-files.com/6624ff6a5db57a668993dd5e/66a089b619ffc49973b93670_Lobby%2001.webp',
            'https://cdn.prod.website-files.com/6624ff6a5db57a668993dd4c/66be205fdad91089de377d75_Hotel%20Mulia%20Senayan%2C%20Jakarta%20-%20Bleu8%20-%20Pool.webp',
            'https://upload.wikimedia.org/wikipedia/commons/6/6e/Hotel_Mulia_dan_Menpora_-_panoramio.jpg',
            'https://ak-d.tripcdn.com/images/0220v120009i4a1r6023F.jpg',
            'https://ak-d.tripcdn.com/images/0221o120009i4a5xq346B.jpg',
        ],
        'rooms' => [
            'grandeur' => [
                'https://cdn.prod.website-files.com/6624ff6a5db57a668993dd5e/673d82b32d5b75a44eee4673_Grandeur.webp',
                'https://cdn.prod.website-files.com/6624ff6a5db57a668993dd5e/66a0821839be4a92bcf32a30_Grandeur02.webp',
            ],
            'mulia-executive' => [
                'https://cdn.prod.website-files.com/6624ff6a5db57a668993dd5e/66a081a66c64bea0d0aebd4f_Mulia%20Executive%2001.webp',
            ],
            'mulia-suite' => [
                'https://cdn.prod.website-files.com/6624ff6a5db57a668993dd5e/673d82ee51e72fc6491fe28d_signature%20room.webp',
                'https://cdn.prod.website-files.com/6624ff6a5db57a668993dd5e/673d8286d5a76f5edb5e47db_Splendor%20Room.webp',
            ],
        ],
    ],

    'the-sultan-hotel-residence-jakarta' => [
        'featured' => 'https://sultanjakarta.com/wp-content/uploads/2024/10/The-Sultan-10.webp',
        'gallery' => [
            'https://sultanjakarta.com/wp-content/uploads/2024/10/The-Sultan-3-scaled.webp',
            'https://sultanjakarta.com/wp-content/uploads/2024/10/The-Sultan-6-scaled.webp',
            'https://sultanjakarta.com/wp-content/uploads/2024/10/The-Sultan-9-scaled.webp',
            'https://sultanjakarta.com/wp-content/uploads/2023/10/IKP_3746.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/9/96/The_Sultan_-_panoramio.jpg',
        ],
        'rooms' => [
            'deluxe-garden-view' => [
                'https://sultanjakarta.com/wp-content/uploads/2023/09/Sultan_Deluxe_Garden-Tower.jpg',
            ],
            'premier-city-view' => [
                'https://sultanjakarta.com/wp-content/uploads/2023/09/Sultan_Executive-Floor-with-logo_medium-size.jpg',
                'https://sultanjakarta.com/wp-content/uploads/2023/09/SHR_1359.jpg',
            ],
            'executive-suite-sultan' => [
                'https://sultanjakarta.com/wp-content/uploads/2023/09/Lagoon-Suite.jpg',
                'https://sultanjakarta.com/wp-content/uploads/2023/09/Sultan_Lanais-1-bed-room.jpg',
            ],
        ],
    ],

    'shangri-la-hotel-jakarta' => [
        'featured' => 'https://ak-d.tripcdn.com/images/0204v120008qxt6f40F61_R_500_400_R5.webp',
        'gallery' => [
            'https://ak-d.tripcdn.com/images/0204v120008qxt6f40F61.jpg',
            'https://ak-d.tripcdn.com/images/0204c12000047skca2B37.jpg',
            'https://ak-d.tripcdn.com/images/02058120009ifgk55008E.jpg',
            'https://ak-d.tripcdn.com/images/0200x1200082bff33AE58.jpg',
            'https://ak-d.tripcdn.com/images/0226312000b6pv62jA628.jpg',
        ],
        'rooms' => [
            'deluxe-room-sl' => [
                'https://ak-d.tripcdn.com/images/0223t120008b1i05s1D2B.jpg',
                'https://ak-d.tripcdn.com/images/022361200084aglxd10EA.jpg',
            ],
            'horizon-club-room' => [
                'https://ak-d.tripcdn.com/images/0222h120004cebd9wA8EC.jpg',
                'https://ak-d.tripcdn.com/images/0230n12000rm41ixb07AA.jpg',
            ],
            'specialty-suite' => [
                'https://ak-d.tripcdn.com/images/0231l12000rl0uoel1D34.jpg',
                'https://ak-d.tripcdn.com/images/0231e12000qai9dq4DA9E.jpg',
            ],
        ],
    ],

    'the-ritz-carlton-pacific-place' => [
        'featured' => 'https://cache.marriott.com/content/dam/marriott-renditions/JKTRT/jktrt-exterior-4009-hor-wide.jpg',
        'gallery' => [
            'https://cache.marriott.com/content/dam/marriott-renditions/JKTRT/jktrt-outdoor-pool-4029-hor-wide.jpg',
            'https://cache.marriott.com/content/dam/marriott-renditions/JKTRT/jktrt-club-lounge-3998-hor-wide.jpg',
            'https://cache.marriott.com/content/dam/marriott-renditions/JKTRT/jktrt-treatment-room-4044-hor-wide.jpg',
            'https://cache.marriott.com/content/dam/marriott-renditions/JKTRT/jktrt-pasola-bar-4025-hor-clsc.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/e/ef/Ritz_Carlton_Pacific_Place_%28Nov_2024%29.jpg',
        ],
        'rooms' => [
            'grand-room' => [
                'https://cache.marriott.com/content/dam/marriott-renditions/JKTRT/jktrt-suite-living-4023-hor-wide.jpg',
                'https://ak-d.tripcdn.com/images/0221d120009eiw4rpC5A6.jpg',
            ],
            'club-room-rc' => [
                'https://cache.marriott.com/content/dam/marriott-renditions/JKTRT/jktrt-club-lounge-3994-hor-wide.jpg',
                'https://cache.marriott.com/content/dam/marriott-renditions/JKTRT/jktrt-club-lounge-3997-hor-wide.jpg',
            ],
            'executive-suite-rc' => [
                'https://cache.marriott.com/content/dam/marriott-renditions/JKTRT/jktrt-presidential-suite-4035-hor-wide.jpg',
                'https://ak-d.tripcdn.com/images/02267120009eiw553CF4C.jpg',
            ],
        ],
    ],

    'holiday-inn-express-jakarta-international-expo' => [
        'featured' => 'https://ak-d.tripcdn.com/images/0223j120009bydjz7FB1C.jpg',
        'gallery' => [
            'https://ak-d.tripcdn.com/images/0220l12000l7rqijo3161.jpg',
            'https://ak-d.tripcdn.com/images/0226012000l7rqidi3154.jpg',
            'https://ak-d.tripcdn.com/images/0225v120008yh1xw2B900.jpg',
            'https://ak-d.tripcdn.com/images/02273120009bydjzb48FF.jpg',
            'https://ak-d.tripcdn.com/images/02257120008yh1ygg7FD3.jpg',
        ],
        'rooms' => [
            'standard-queen-hie' => [
                'https://ak-d.tripcdn.com/images/02245120008yh1x0eAF67.jpg',
                'https://ak-d.tripcdn.com/images/0226o120008yh1vmj1876.jpg',
            ],
            'standard-twin-hie' => [
                'https://ak-d.tripcdn.com/images/0222m120009kme550A9C0.jpg',
                'https://ak-d.tripcdn.com/images/0225k120009kmen9900FA.jpg',
            ],
        ],
    ],

    'b-hotel-jakarta' => [
        'featured' => 'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONKemayoran/Overview/AKCH-Facade.jpg',
        'gallery' => [
            'https://images.archipelagohotels.com/astoninternational/Images/v1/AstonKemayoran/Facilities/LOBBY_1-47901.png',
            'https://images.archipelagohotels.com/astoninternational/Images/v1/AstonKemayoran/Facilities/SkyViewLvl27th.jpeg',
            'https://images.archipelagohotels.com/astoninternational/Images/v1/AstonKemayoran/Facilities/SkyViewRooftop.jpg',
        ],
        'rooms' => [
            'superior-king-bhotel' => [
                'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONKemayoran/Gallery/StudioDouble.jpg',
            ],
            'deluxe-twin-bhotel' => [
                'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONKemayoran/Gallery/StudioTwin.jpg',
            ],
            'family-suite-bhotel' => [
                'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONKemayoran/Gallery/StudioTriple.jpg',
                'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONKemayoran/Gallery/StudioPlus.jpg',
            ],
        ],
    ],

    'aston-inn-kemayoran' => [
        'featured' => 'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONKemayoran/Overview/AKCH-Facade.jpg',
        'gallery' => [
            'https://images.archipelagohotels.com/astoninternational/Images/v1/AstonKemayoran/Facilities/LOBBY_1-47901.png',
            'https://images.archipelagohotels.com/astoninternational/Images/v1/AstonKemayoran/Facilities/SilangitRestaurant-1Rev.png',
            'https://images.archipelagohotels.com/astoninternational/Images/v1/AstonKemayoran/Facilities/SkyViewLvl27th.jpeg',
            'https://images.archipelagohotels.com/astoninternational/Images/v1/AstonKemayoran/Meeting/Ballroom-1.png',
            'https://ak-d.tripcdn.com/images/02205120009i77l420C34.jpg',
        ],
        'rooms' => [
            'superior-room-aston' => [
                'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONKemayoran/Gallery/StudioDouble.jpg',
                'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONKemayoran/Gallery/StudioTwin.jpg',
            ],
            'deluxe-room-aston' => [
                'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONKemayoran/Gallery/StudioPlus.jpg',
                'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONKemayoran/Gallery/StudioTriple.jpg',
            ],
        ],
    ],

    'harris-hotel-kelapa-gading' => [
        'featured' => 'https://ak-d.tripcdn.com/images/0231p12000s2bjyspDD7B.jpg',
        'gallery' => [
            'https://ak-d.tripcdn.com/images/0220j120008c0kh240880.jpg',
            'https://ak-d.tripcdn.com/images/0221p1200090thl0e38F8.jpg',
            'https://ak-d.tripcdn.com/images/02257120008xqwxb23FAE.jpg',
            'https://ak-d.tripcdn.com/images/02220120008xqwyfw87A1.jpg',
            'https://ak-d.tripcdn.com/images/0232c12000i1xp0sx3B05.jpg',
        ],
        'rooms' => [
            'harris-room' => [
                'https://ak-d.tripcdn.com/images/02254120008gua0h2B53F.jpg',
                'https://ak-d.tripcdn.com/images/0222j1200090thq8g3FE6.jpg',
            ],
            'harris-family' => [
                'https://ak-d.tripcdn.com/images/0222d12000anepr0r1351.jpg',
                'https://ak-d.tripcdn.com/images/0222712000q1m9dns259A.jpg',
            ],
        ],
    ],

    'pop-hotel-kemayoran' => [
        'featured' => 'https://ak-d.tripcdn.com/images/0226d12000k6lbnsd14C0_R_500_400_R5.webp',
        'gallery' => [
            'https://ak-d.tripcdn.com/images/0221f12000gexty147384_R_500_400_R5.webp',
            'https://ak-d.tripcdn.com/images/0220u12000aq43fsrDEE2.jpg',
            'https://ak-d.tripcdn.com/images/0221512000ae66hl23996.jpg',
            'https://ak-d.tripcdn.com/images/0222612000cawfac33580.jpg',
        ],
        'rooms' => [
            'pop-room' => [
                'https://ak-d.tripcdn.com/images/0222j12000ae66gh248D4.jpg',
                'https://ak-d.tripcdn.com/images/0222o12000kxw33uj091D.jpg',
            ],
        ],
    ],

    'the-grove-suites-bsd-city' => [
        'featured' => 'https://ak-d.tripcdn.com/images/0220m12000aqgu2pl1CA1.jpg',
        'gallery' => [
            'https://ak-d.tripcdn.com/images/0220j12000kz2ngqwE424.jpg',
            'https://ak-d.tripcdn.com/images/0220312000kz2nklj8204.jpg',
            'https://ak-d.tripcdn.com/images/0222u12000kwkg6giA3D9.jpg',
            'https://images.archipelagohotels.com/astoninternational/Images/v1/TheGroveSuitesbyGRANDASTON/Facilities/TheGroveSuites_VerandaRestaurant_1.jpg',
            'https://images.archipelagohotels.com/astoninternational/Images/v1/TheGroveSuitesbyGRANDASTON/Facilities/TheGroveSuites_HighTea.jpg',
        ],
        'rooms' => [
            'one-bedroom-suite' => [
                'https://images.archipelagohotels.com/astoninternational/Images/v1/TheGroveSuitesbyGRANDAston/Room/Room.jpeg',
                'https://images.archipelagohotels.com/astoninternational/Images/v1/TheGroveSuitesbyGRANDAston/Room/LR.jpeg',
            ],
            'two-bedroom-suite' => [
                'https://images.archipelagohotels.com/astoninternational/Images/v1/TheGroveSuitesbyGRANDASTON/Room/TheGroveSuites_FamilySuitesRoom_1.jpg',
                'https://images.archipelagohotels.com/astoninternational/Images/v1/TheGroveSuitesbyGRANDASTON/Room/TheGroveSuites_FamilySuitesRoom_Suite_2.jpg',
            ],
        ],
    ],

    'aryaduta-bsd' => [
        'featured' => 'https://ak-d.tripcdn.com/images/220l0u000000jb43v34DB_R_500_400_R5.webp',
        'gallery' => [
            'https://ak-d.tripcdn.com/images/0223112000an15f61A00A_R_500_400_R5.webp',
            'https://ak-d.tripcdn.com/images/220s0u000000jhts190B3_R_500_400_R5.webp',
            'https://ak-d.tripcdn.com/images/22030u000000jcoerB40E_R_500_400_R5.webp',
        ],
        'rooms' => [
            'deluxe-room-aryaduta' => [
                'https://ak-d.tripcdn.com/images/0226n12000an15bgz20C1_R_500_400_R5.webp',
                'https://ak-d.tripcdn.com/images/0223k12000amri1nn1E52_R_500_400_R5.webp',
            ],
            'premier-lagoon' => [
                'https://ak-d.tripcdn.com/images/220t0g0000007z9jx09E6_R_500_400_R5.webp',
                'https://ak-d.tripcdn.com/images/220q0v000000jzyd1A107_R_500_400_R5.webp',
            ],
            'aryaduta-suite' => [
                'https://ak-d.tripcdn.com/images/0583012000csst8055167_R_500_400_R5.webp',
                'https://ak-d.tripcdn.com/images/0586012000csstcpm1AD8_R_500_400_R5.webp',
            ],
        ],
    ],

    'swiss-belhotel-serpong' => [
        'featured' => 'https://ak-d.tripcdn.com/images/02009120008m1bbvzE5DE.jpg',
        'gallery' => [
            'https://ak-d.tripcdn.com/images/0200e120008m1d6hb652A.jpg',
            'https://ak-d.tripcdn.com/images/0200y120008m1gpkvF2F6.jpg',
            'https://ak-d.tripcdn.com/images/02038120008m1hgu2AB3C.jpg',
            'https://irp.cdn-website.com/1074ee8b/dms3rep/multi/swiss-belhotel-serpong-indonesia-convention-exhibition-ice.jpeg',
            'https://irp.cdn-website.com/1074ee8b/dms3rep/multi/swiss-belhotel-serpong-tangerang-selatan-meeting-room-4.jpg',
        ],
        'rooms' => [
            'deluxe-swiss' => [
                'https://ak-d.tripcdn.com/images/0200i120008m1cb69CC26.jpg',
                'https://ak-d.tripcdn.com/images/0202y120008m1c8yaEBC1.jpg',
            ],
            'grand-deluxe-swiss' => [
                'https://ak-d.tripcdn.com/images/0200m120008m1cbzb7631.jpg',
                'https://ak-d.tripcdn.com/images/02052120008m1c8qd2491.jpg',
            ],
            'junior-suite-swiss' => [
                'https://ak-d.tripcdn.com/images/0205d120008m1b80c9479.jpg',
                'https://ak-d.tripcdn.com/images/0202v120008m1alo1F012.jpg',
            ],
        ],
    ],

    'mercure-serpong-alam-sutera' => [
        'featured' => 'https://www.ahstatic.com/photos/9078_ho_00_p_2048x1536.jpg',
        'gallery' => [
            'https://www.ahstatic.com/photos/9078_ho_01_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/9078_ho_02_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/9078_sw_00_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/9078_bab001_00_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/9078_br_00_p_2048x1536.jpg',
        ],
        'rooms' => [
            'superior-mercure-as' => [
                'https://www.ahstatic.com/photos/9078_rokga_00_p_2048x1536.jpg',
                'https://www.ahstatic.com/photos/9078_rotwa_00_p_2048x1536.jpg',
            ],
            'privilege-mercure-as' => [
                'https://www.ahstatic.com/photos/9078_rokge_00_p_2048x1536.jpg',
                'https://www.ahstatic.com/photos/9078_roskc_00_p_2048x1536.jpg',
            ],
        ],
    ],

    'santika-premiere-bsd' => [
        'featured' => 'https://production-santika.ap-south-1.linodeobjects.com/media/images/hotel-santika-premiere/hotel-santika-premiere-ice-bsd-city/mobile-apps/hotel-gallery/hotel-gallery-cover.jpg',
        'gallery' => [
            'https://production-santika.ap-south-1.linodeobjects.com/media/images/hotel-santika-premiere/hotel-santika-premiere-ice-bsd-city/mobile-apps/hotel-gallery/hotel-gallery-(1).jpg',
            'https://production-santika.ap-south-1.linodeobjects.com/media/images/hotel-santika-premiere/hotel-santika-premiere-ice-bsd-city/mobile-apps/hotel-gallery/hotel-gallery-(5).jpg',
            'https://production-santika.ap-south-1.linodeobjects.com/media/images/hotel-santika-premiere/hotel-santika-premiere-ice-bsd-city/mobile-apps/hotel-gallery/hotel-gallery-(9).jpg',
            'https://production-santika.ap-south-1.linodeobjects.com/media/images/hotel-santika-premiere/hotel-santika-premiere-ice-bsd-city/mobile-apps/hotel-gallery/hotel-gallery-(10).jpg',
            'https://production-santika.ap-south-1.linodeobjects.com/media/images/hotel-santika-premiere/hotel-santika-premiere-ice-bsd-city/mobile-apps/hotel-gallery/hotel-gallery-(11).jpg',
        ],
        'rooms' => [
            'superior-santika-bsd' => [
                'https://production-santika.ap-south-1.linodeobjects.com/media/images/hotel-santika-premiere/hotel-santika-premiere-ice-bsd-city/mobile-apps/hotel-gallery/hotel-gallery-(2).jpg',
            ],
            'deluxe-santika-bsd' => [
                'https://production-santika.ap-south-1.linodeobjects.com/media/images/hotel-santika-premiere/hotel-santika-premiere-ice-bsd-city/mobile-apps/hotel-gallery/hotel-gallery-(3).jpg',
            ],
            'premier-suite-santika' => [
                'https://production-santika.ap-south-1.linodeobjects.com/media/images/hotel-santika-premiere/hotel-santika-premiere-ice-bsd-city/mobile-apps/hotel-gallery/hotel-gallery-(13).jpg',
            ],
        ],
    ],

    'pullman-jakarta-pik-avenue' => [
        'featured' => 'https://www.ahstatic.com/photos/b590_ho_00_p_2048x1536.jpg',
        'gallery' => [
            'https://www.ahstatic.com/photos/b590_ho_01_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/b590_ho_02_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/b590_ho_03_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/b590_sw_00_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/b590_bab001_00_p_2048x1536.jpg',
        ],
        'rooms' => [
            'superior-pullman' => [
                'https://www.ahstatic.com/photos/b590_rokga_00_p_2048x1536.jpg',
                'https://www.ahstatic.com/photos/b590_rotwb_00_p_2048x1536.jpg',
            ],
            'executive-pullman' => [
                'https://www.ahstatic.com/photos/b590_rokgb_00_p_2048x1536.jpg',
                'https://www.ahstatic.com/photos/b590_rokge_00_p_2048x1536.jpg',
            ],
            'junior-suite-pullman' => [
                'https://www.ahstatic.com/photos/b590_roska_00_p_2048x1536.jpg',
                'https://www.ahstatic.com/photos/b590_roskc_00_p_2048x1536.jpg',
            ],
        ],
    ],

    'swiss-belhotel-mangga-besar' => [
        'featured' => 'https://ak-d.tripcdn.com/images/0223g12000djs61bi0A1F_R_500_400_R5.webp',
        'gallery' => [
            'https://ak-d.tripcdn.com/images/02038120008m1hgu2AB3C.jpg',
            'https://ak-d.tripcdn.com/images/0202d120008m1bsnu6B73.jpg',
            'https://ak-d.tripcdn.com/images/0204l120008m1l5cp4559.jpg',
        ],
        'rooms' => [
            'deluxe-swiss-mb' => [
                'https://ak-d.tripcdn.com/images/0200i120008m1cb69CC26.jpg',
                'https://ak-d.tripcdn.com/images/0202y120008m1c8yaEBC1.jpg',
            ],
            'grand-deluxe-swiss-mb' => [
                'https://ak-d.tripcdn.com/images/0205d120008m1b80c9479.jpg',
                'https://ak-d.tripcdn.com/images/0200m120008m1cbzb7631.jpg',
            ],
        ],
    ],

    'aston-pluit-hotel-residence' => [
        'featured' => 'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONPluit/Overview/pluit-facade.jpg',
        'gallery' => [
            'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONPluit/Facilities/gym1.jpg',
            'https://images.archipelagohotels.com/astoninternational/v2/Images/AstonPluit/Facilities/Ares_BAR1.jpg',
            'https://images.archipelagohotels.com/astoninternational/v2/Images/AstonPluit/Facilities/Ares_BAR2.jpg',
            'https://ak-d.tripcdn.com/images/0220p12000k71gemw0425.jpg',
            'https://ak-d.tripcdn.com/images/0223x120009ztyw9b6201.jpg',
        ],
        'rooms' => [
            'superior-aston-pluit' => [
                'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONPluit/Room/StudioPlusDouble.jpg',
                'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONPluit/Room/StudioPlusTwin.jpg',
            ],
            'deluxe-suite-aston-pluit' => [
                'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONPluit/Room/SuiteRoom.jpg',
                'https://images.archipelagohotels.com/astoninternational/Images/v1/ASTONPluit/Gallery/SuiteRoom2.jpg',
            ],
        ],
    ],

    'mercure-convention-centre-ancol' => [
        'featured' => 'https://www.ahstatic.com/photos/5473_ho_00_p_2048x1536.jpg',
        'gallery' => [
            'https://www.ahstatic.com/photos/5473_ho_01_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/5473_ho_02_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/5473_bab002_00_p_2048x1536.jpg',
            'https://www.ahstatic.com/photos/5473_br_00_p_2048x1536.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/f/f9/Mercure_Hotel_Ancol_Jakarta_-_panoramio.jpg',
        ],
        'rooms' => [
            'superior-mercure-ancol' => [
                'https://www.ahstatic.com/photos/5473_rokgabc_00_p_2048x1536.jpg',
                'https://www.ahstatic.com/photos/5473_rokgb_00_p_2048x1536.jpg',
            ],
            'deluxe-lagoon-ancol' => [
                'https://www.ahstatic.com/photos/5473_rokgaef_00_p_2048x1536.jpg',
                'https://www.ahstatic.com/photos/5473_rokge_00_p_2048x1536.jpg',
            ],
            'family-studio-ancol' => [
                'https://www.ahstatic.com/photos/5473_rosla_00_p_2048x1536.jpg',
                'https://www.ahstatic.com/photos/5473_roslb_00_p_2048x1536.jpg',
            ],
        ],
    ],

    'holiday-inn-jakarta-pik' => [
        'featured' => 'https://ak-d.tripcdn.com/images/0220m120009euh1e39D3D.jpg',
        'gallery' => [
            'https://ak-d.tripcdn.com/images/0220x120009cctyffE143.jpg',
            'https://ak-d.tripcdn.com/images/0223z120009eugr2nBCC7.jpg',
            'https://ak-d.tripcdn.com/images/0223n120008ku9ch62CD4.jpg',
            'https://ak-d.tripcdn.com/images/0222e12000bmtmp2vF2EB.jpg',
            'https://ak-d.tripcdn.com/images/0226z12000a8ytwa38239.jpg',
        ],
        'rooms' => [
            'standard-holiday-inn' => [
                'https://ak-d.tripcdn.com/images/0221k12000as1csthD339.jpg',
                'https://ak-d.tripcdn.com/images/0221n120009c3sqslC132.jpg',
            ],
            'executive-holiday-inn' => [
                'https://ak-d.tripcdn.com/images/0224512000as1d5vh5475.jpg',
                'https://ak-d.tripcdn.com/images/0225v12000aaqzo2m490E.jpg',
            ],
            'one-bedroom-suite-hi' => [
                'https://ak-d.tripcdn.com/images/0223i12000ajl4gl46DBC.jpg',
                'https://ak-d.tripcdn.com/images/0226z12000k7jgbohA852.jpg',
            ],
        ],
    ],
];

function detectExt(string $contentType, string $url): string
{
    if (str_contains($contentType, 'jpeg') || str_contains($contentType, 'jpg')) {
        return 'jpg';
    }
    if (str_contains($contentType, 'png')) {
        return 'png';
    }
    if (str_contains($contentType, 'webp')) {
        return 'webp';
    }
    if (str_contains($contentType, 'gif')) {
        return 'gif';
    }
    // fallback by URL extension
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? ($ext === 'jpeg' ? 'jpg' : $ext) : 'jpg';
}

function fetchUrl(string $url, string $targetPath): bool
{
    @mkdir(dirname($targetPath), 0755, true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/png,image/jpeg,image/gif,*/*;q=0.8'],
        CURLOPT_HEADER => true,
    ]);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $code = $info['http_code'] ?? 0;
    $headerSize = $info['header_size'] ?? 0;
    $contentType = $info['content_type'] ?? '';
    curl_close($ch);

    if ($code !== 200 || ! is_string($response) || strlen($response) <= $headerSize) {
        echo "  ! HTTP $code or empty body for $url\n";

        return false;
    }
    $body = substr($response, $headerSize);
    if (! str_starts_with($contentType, 'image/')) {
        echo "  ! Non-image content-type ($contentType) for $url\n";

        return false;
    }

    $ext = detectExt($contentType, $url);
    $finalPath = preg_replace('/\.[^.]+$/', '.'.$ext, $targetPath);
    if ($finalPath === null) {
        $finalPath = $targetPath;
    }
    file_put_contents($finalPath, $body);
    echo '  ✓ '.basename($finalPath).' ('.number_format(strlen($body) / 1024, 1)." KB)\n";

    return true;
}

foreach ($photos as $slug => $data) {
    echo "[$slug]\n";
    if (! empty($data['featured'])) {
        fetchUrl($data['featured'], "$root/$slug/featured.jpg");
    }
    if (! empty($data['gallery'])) {
        foreach ($data['gallery'] as $i => $url) {
            fetchUrl($url, "$root/$slug/gallery/".($i + 1).'.jpg');
        }
    }
    if (! empty($data['rooms'])) {
        foreach ($data['rooms'] as $roomSlug => $urls) {
            if (! is_array($urls)) {
                continue;
            }
            foreach ($urls as $i => $url) {
                fetchUrl($url, "$root/$slug/rooms/$roomSlug/".($i + 1).'.jpg');
            }
        }
    }
}
