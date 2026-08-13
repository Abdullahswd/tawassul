<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

// جلب جميع الخدمات من قاعدة البيانات
$all_services = getAllServices();

// التأكد من أن البيانات مصفوفة (حتى لو فارغة)
if (!is_array($all_services)) {
  $all_services = [];
}

// تحويل إلى JSON مباشرة (بدون htmlspecialchars)
$all_services_json = json_encode($all_services, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
  <title>تواصل - منصة الخدمات الأكاديمية الشاملة</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <link rel="stylesheet" href="assets/css/global.css">
  <style>
    body {
      overflow-x: hidden;
      width: 100%;
    }

    .hero {
      background: linear-gradient(145deg, var(--bg-main) 0%, rgba(79, 70, 229, 0.05) 100%);
      border-radius: 48px;
      margin: 20px 16px 0 16px;
    }

    @media (min-width: 768px) {
      .hero {
        margin: 20px 20px 0 20px;
      }
    }

    .feature-card {
      background: var(--bg-card);
      border-radius: 32px;
      padding: 28px 20px;
      text-align: center;
      transition: all 0.4s;
      border: 1px solid var(--border-color);
      height: 100%;
    }

    .feature-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 25px 40px -12px rgba(79, 70, 229, 0.2);
      border-color: var(--primary);
    }

    .feature-icon {
      width: 70px;
      height: 70px;
      background: var(--primary-light);
      border-radius: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 34px;
      margin: 0 auto 20px;
      transition: all 0.3s;
    }

    @media (min-width: 768px) {
      .feature-icon {
        width: 80px;
        height: 80px;
        font-size: 40px;
      }
    }

    .feature-card:hover .feature-icon {
      background: var(--primary);
      color: white;
      transform: scale(1.05);
    }

    .academics-section {
      background: linear-gradient(145deg, var(--bg-main) 0%, rgba(79, 70, 229, 0.02) 100%);
      border-radius: 48px;
      margin: 40px 16px;
      padding: 40px 16px;
      overflow: hidden;
    }

    @media (min-width: 768px) {
      .academics-section {
        margin: 40px 20px;
        padding: 60px 20px;
      }
    }

    .academicsSwiper {
      width: 100%;
      overflow: visible !important;
      padding: 10px 5px 40px 5px;
    }

    .swiper-slide {
      width: auto !important;
      max-width: 100%;
      height: auto;
    }

    .academic-card {
      background: var(--bg-card);
      border-radius: 28px;
      padding: 24px 16px;
      text-align: center;
      transition: all 0.4s;
      box-shadow: 0 15px 30px -8px rgba(0, 0, 0, 0.1);
      border: 1px solid var(--border-color);
      height: 100%;
      width: 100%;
      max-width: 280px;
      margin: 0 auto;
    }

    @media (min-width: 1024px) {
      .academic-card {
        max-width: 300px;
      }
    }

    .academic-img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      margin: 0 auto 16px;
      border: 3px solid var(--primary-light);
      transition: all 0.3s;
    }

    @media (min-width: 768px) {
      .academic-img {
        width: 120px;
        height: 120px;
      }
    }

    .academic-card:hover .academic-img {
      transform: scale(1.05);
      border-color: var(--primary);
    }

    .stars {
      direction: ltr;
      display: inline-block;
      color: #fbbf24;
      letter-spacing: 3px;
      font-size: 1.1rem;
      margin: 12px 0;
    }

    .contact-btn {
      display: inline-block;
      padding: 8px 20px;
      background: linear-gradient(135deg, var(--primary) 0%, #5b4dff 100%);
      color: white;
      border-radius: 40px;
      font-weight: 700;
      font-size: 14px;
      transition: all 0.3s;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      margin-top: 12px;
    }

    .contact-btn:hover {
      transform: scale(1.05);
      box-shadow: 0 8px 18px rgba(79, 70, 229, 0.4);
      letter-spacing: 0.5px;
    }

    .step-number {
      width: 70px;
      height: 70px;
      background: var(--primary-light);
      color: var(--primary);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      font-weight: 900;
      margin: 0 auto 16px;
      transition: all 0.3s;
    }

    @media (min-width: 768px) {
      .step-number {
        width: 80px;
        height: 80px;
        font-size: 32px;
      }
    }

    .step-card {
      max-width: 250px;
      transition: all 0.3s;
      padding: 20px;
      border-radius: 32px;
    }

    .step-card:hover {
      transform: translateY(-5px);
      background: var(--bg-card);
      border-color: var(--border-color);
    }

    .step-card:hover .step-number {
      background: var(--primary);
      color: white;
      transform: scale(1.05);
    }

    .mobile-menu {
      position: fixed;
      top: 70px;
      right: -100%;
      width: 80%;
      max-width: 320px;
      height: calc(100vh - 70px);
      background: var(--bg-card);
      backdrop-filter: blur(20px);
      box-shadow: -5px 0 30px rgba(0, 0, 0, 0.2);
      transition: right 0.3s ease-in-out;
      z-index: 999;
      padding: 24px 20px;
      border-radius: 24px 0 0 24px;
      overflow-y: auto;
    }

    .mobile-menu.active {
      right: 0;
    }

    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(3px);
      z-index: 998;
      visibility: hidden;
      opacity: 0;
      transition: 0.2s;
    }

    .overlay.active {
      visibility: visible;
      opacity: 1;
    }

    .swiper-button-next,
    .swiper-button-prev {
      width: 36px;
      height: 36px;
      background: rgba(255, 255, 255, 0.7);
      backdrop-filter: blur(8px);
      border-radius: 50%;
      transition: all 0.3s;
    }

    .swiper-button-next:after,
    .swiper-button-prev:after {
      font-size: 18px;
      font-weight: bold;
    }

    .swiper-button-next:hover,
    .swiper-button-prev:hover {
      background: var(--primary);
      color: white;
      transform: scale(1.1);
    }

    @media (min-width: 768px) {

      .swiper-button-next,
      .swiper-button-prev {
        width: 44px;
        height: 44px;
      }

      .swiper-button-next:after,
      .swiper-button-prev:after {
        font-size: 20px;
      }
    }

    .swiper-pagination-bullet-active {
      background: var(--primary) !important;
    }

    .fade-up {
      opacity: 0;
      transform: translateY(30px);
      animation: fadeUp 0.8s forwards;
    }

    @keyframes fadeUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .expand-services {
      transition: max-height 0.5s ease, opacity 0.3s ease;
      max-height: 0;
      opacity: 0;
      overflow: hidden;
    }

    .expand-services.open {
      max-height: 5000px;
      opacity: 1;
    }

    .view-toggle-btn {
      transition: all 0.3s;
    }

    .view-toggle-btn:hover {
      transform: scale(1.02);
    }
  </style>
</head>

<body class="overflow-x-hidden">

  <div class="overlay" id="menuOverlay"></div>

  <!-- شريط التنقل -->
  <nav class="relative flex items-center justify-between flex-wrap p-3 md:p-4 lg:px-8 bg-[var(--bg-card)] border-b border-[var(--border-color)]">
    <div class="flex items-center gap-2 text-xl md:text-2xl font-extrabold">
      <span class="text-[var(--primary)]">🎓</span>
      <span class="text-[var(--text-primary)]">تواصل</span>
    </div>

    <div class="hidden md:flex items-center gap-5 lg:gap-6 text-sm font-semibold">
      <a href="#features" class="text-[var(--text-secondary)] hover:text-[var(--primary)] transition">المميزات</a>
      <a href="#how-it-works" class="text-[var(--text-secondary)] hover:text-[var(--primary)] transition">كيف نعمل</a>
      <a href="#academics" class="text-[var(--text-secondary)] hover:text-[var(--primary)] transition">أكاديميونا</a>
      <a href="packages.php" class="text-[var(--text-secondary)] hover:text-[var(--primary)] transition">الباقات</a>
      <a href="reviews.php" class="text-[var(--text-secondary)] hover:text-[var(--primary)] transition">التقييمات</a>
    </div>

    <div class="flex items-center gap-2 sm:gap-3">
      <button class="dark-toggle text-xl bg-transparent border-none cursor-pointer px-1">🌙</button>
      <a href="login.php" class="px-3 py-1.5 sm:px-4 sm:py-2 rounded-full border border-[var(--primary)] text-[var(--primary)] text-xs sm:text-sm font-bold hover:bg-[var(--primary)] hover:text-white transition whitespace-nowrap">دخول</a>
      <a href="register.php" class="px-3 py-1.5 sm:px-4 sm:py-2 rounded-full bg-gradient-to-l from-[var(--primary)] to-[#5b4dff] text-white text-xs sm:text-sm font-bold shadow-md hover:scale-105 transition whitespace-nowrap">إنشاء حساب</a>
      <button id="menuToggle" class="md:hidden text-2xl focus:outline-none pr-1">☰</button>
    </div>
  </nav>

  <div class="mobile-menu flex flex-col gap-5" id="mobileMenu">
    <a href="#features" class="text-lg font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2">المميزات</a>
    <a href="#how-it-works" class="text-lg font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2">كيف نعمل</a>
    <a href="#academics" class="text-lg font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2">أكاديميونا</a>
    <a href="packages.php" class="text-lg font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2">الباقات</a>
    <a href="reviews.php" class="text-lg font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2">التقييمات</a>
  </div>

  <!-- Hero Section -->
  <header class="hero fade-up px-4 py-12 md:py-20 text-center">
    <div class="max-w-3xl mx-auto">
      <span class="inline-block px-4 py-1.5 bg-[var(--primary-light)] text-[var(--primary)] rounded-full text-xs md:text-sm font-extrabold mb-4 md:mb-6">🚀 المنصة الأكاديمية رقم 1 في الوطن العربي</span>
      <h1 class="text-4xl md:text-5xl lg:text-6xl font-black mb-4 md:mb-6 bg-gradient-to-l from-[var(--text-primary)] to-[var(--primary)] bg-clip-text text-transparent leading-tight">أنجز أبحاثك ومشاريعك الجامعية بكفاءة واحترافية</h1>
      <p class="text-base md:text-lg text-[var(--text-secondary)] mb-6 md:mb-10 leading-relaxed px-2">نربط الطلاب بنخبة من الأكاديميين المتخصصين لتقديم الدعم والمساندة في كافة متطلبات التحليل الإحصائي، الترجمة، التدقيق اللغوي، والمزيد الموثق.</p>
      <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
        <a href="register.php" class="w-full sm:w-auto btn btn-primary text-base md:text-lg px-6 md:px-8 py-3 md:py-4 rounded-full bg-gradient-to-l from-[var(--primary)] to-[#5b4dff] text-white font-bold shadow-lg hover:scale-105 transition">ابدأ الآن - مجاناً 🚀</a>
        <a href="#features" class="w-full sm:w-auto btn btn-outline text-base md:text-lg px-6 md:px-8 py-3 md:py-4 rounded-full border-2 border-[var(--primary)] text-[var(--primary)] font-bold hover:bg-[var(--primary)] hover:text-white transition">📋 تصفح الخدمات المتوفرة</a>
      </div>
      <p class="text-xs md:text-sm text-[var(--text-muted)] mt-6 md:mt-8">🔒 خدمات موثقة بضمان الجودة | أكثر من 15,000 طالب استفادوا</p>
    </div>
  </header>

  <!-- Services Highlights -->
  <section id="features" class="py-12 md:py-20 px-4 max-w-7xl mx-auto">
    <div class="text-center mb-10 md:mb-14 fade-up">
      <span class="inline-block bg-[var(--primary-light)] text-[var(--primary)] px-4 py-1.5 rounded-full text-xs md:text-sm font-bold mb-3 md:mb-4">خدماتنا المتكاملة</span>
      <h2 class="text-3xl md:text-4xl font-extrabold mb-3 md:mb-4">كل ما تحتاجه كطالب ودكتور في مكان واحد</h2>
      <p class="text-[var(--text-secondary)] max-w-2xl mx-auto px-2">نقدم مجموعة شاملة من الخدمات الأكاديمية لمساعدتك في كل مرحلة من مراحل بحثك العلمي</p>
    </div>

    <!-- الخدمات الـ 6 الظاهرة -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8" id="visibleServices">
      <div class="feature-card fade-up" style="animation-delay:0.1s">
        <div class="feature-icon">🏗️</div>
        <h3 class="text-xl md:text-2xl font-extrabold mb-2">التأسيس الأكاديمي</h3>
        <p class="text-sm md:text-base text-[var(--text-secondary)] leading-relaxed">نساعدك في اختيار عنوان البحث، صياغة المقترح (Proposal)، وبناء خطة بحثية متكاملة وقوية.</p>
      </div>
      <div class="feature-card fade-up" style="animation-delay:0.2s">
        <div class="feature-icon">📝</div>
        <h3 class="text-xl md:text-2xl font-extrabold mb-2">تطوير البحث العلمي</h3>
        <p class="text-sm md:text-base text-[var(--text-secondary)] leading-relaxed">كتابة الإطار النظري، تلخيص الدراسات السابقة، وإعادة الصياغة الأكاديمية باحترافية عالية.</p>
      </div>
      <div class="feature-card fade-up" style="animation-delay:0.3s">
        <div class="feature-icon">✏️</div>
        <h3 class="text-xl md:text-2xl font-extrabold mb-2">التدقيق والتحرير</h3>
        <p class="text-sm md:text-base text-[var(--text-secondary)] leading-relaxed">تدقيق لغوي ونحوي شامل، وتنسيق الأبحاث حسب أدلة الجامعات المحلية والدولية.</p>
      </div>
      <div class="feature-card fade-up" style="animation-delay:0.4s">
        <div class="feature-icon">📊</div>
        <h3 class="text-xl md:text-2xl font-extrabold mb-2">التحليل الإحصائي</h3>
        <p class="text-sm md:text-base text-[var(--text-secondary)] leading-relaxed">تحليل البيانات باستخدام SPSS، اختبار الفرضيات، وتفسير النتائج بدقة وموثوقية.</p>
      </div>
      <div class="feature-card fade-up" style="animation-delay:0.5s">
        <div class="feature-icon">🌐</div>
        <h3 class="text-xl md:text-2xl font-extrabold mb-2">الترجمة الأكاديمية</h3>
        <p class="text-sm md:text-base text-[var(--text-secondary)] leading-relaxed">ترجمة معتمدة للأبحاث والمصطلحات العلمية من وإلى اللغة الإنجليزية بجودة أكاديمية.</p>
      </div>
      <div class="feature-card fade-up" style="animation-delay:0.6s">
        <div class="feature-icon">📰</div>
        <h3 class="text-xl md:text-2xl font-extrabold mb-2">النشر العلمي</h3>
        <p class="text-sm md:text-base text-[var(--text-secondary)] leading-relaxed">المساعدة في تجهيز الأبحاث للنشر في المجلات المحكمة ذات التأثير العالي (ISI/Scopus).</p>
      </div>
    </div>

    <!-- زر عرض كل الخدمات -->
    <div class="text-center mt-10 fade-up">
      <button id="toggleAllServices" class="btn btn-primary view-toggle-btn" style="padding:14px 40px;font-size:16px;border-radius:40px;font-weight:800;cursor:pointer;border:none">
        📋 كل الخدمات
      </button>
    </div>

    <!-- القائمة الممتدة لجميع الخدمات من قاعدة البيانات -->
    <div class="expand-services" id="allServices">
      <div style="margin-top:32px;padding-top:32px;border-top:2px dashed var(--border-color)">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8" id="allServicesGrid"></div>
      </div>
    </div>
  </section>

  <!-- Academics Carousel -->
  <section id="academics" class="academics-section">
    <div class="max-w-7xl mx-auto">

      <!-- Section Header -->
      <div class="text-center mb-8 md:mb-12 fade-up">
        <span class="inline-block px-4 py-1.5 bg-[var(--primary-light)] text-[var(--primary)] rounded-full text-xs md:text-sm font-bold mb-3">
          نخبة من الخبراء
        </span>

        <h2 class="text-3xl md:text-4xl font-extrabold mb-3">
          الأستشاريين المتميزون
        </h2>

        <p class="text-[var(--text-secondary)] max-w-2xl mx-auto">
          تعاون مع أفضل الأساتذة والباحثين – اختر من بين خبرائنا المتخصصين
        </p>
      </div>

      <!-- Swiper -->
      <div class="swiper academicsSwiper">

        <div class="swiper-wrapper">

          <!-- Academic 1 -->
          <div class="swiper-slide">
            <div class="academic-card">

              <img
                src="/image/IMG-20260620-WA0014.jpg"
                alt="البروفيسور خليل سعيد الوجيه"
                class="academic-img">

              <h3 class="text-xl md:text-2xl font-extrabold">
                البروفيسور خليل سعيد الوجيه
              </h3>

              <p class="text-[var(--primary)] font-bold text-sm">
                دكتوراه في النمذجه والمحاكاة
              </p>

              <div class="stars">★★★★★</div>

              <p class="text-sm text-[var(--text-secondary)] mb-4">
                الأستاذ الدكتور خليل سعيد الوجيه، رئيس جامعة الرازي، هو أكاديمي يمني بارز حاصل على الدكتوراه في النمذجة والمحاكاة من جامعة الموصل، وشغل سابقاً مناصب قيادية رفيعة منها رئيس جامعة ذمار وعميد كلية الحاسوب بها.
              </p>

              <a href="#" class="contact-btn">
                تواصل معي ←
              </a>

            </div>
          </div>


          <!-- Academic 2 -->
          <div class="swiper-slide">
            <div class="academic-card">

              <img
                src="/image/IMG-20260620-WA0020.jpg"
                alt="د. محمد محمد النفيش"
                class="academic-img">

              <h3 class="text-xl md:text-2xl font-extrabold">
                د. محمد محمد النفيش
              </h3>

              <p class="text-[var(--primary)] font-bold text-sm">
                استاذ اداره الاعمال المساعد
              </p>

              <div class="stars">★★★★★</div>

              <p class="text-sm text-[var(--text-secondary)] mb-4">
                رئيس قسم الاعمال والتجاره الالكترونيه - جامعة الرازي
              </p>

              <a href="#" class="contact-btn">
                تواصل معي ←
              </a>

            </div>
          </div>


          <!-- Academic 3 -->
          <div class="swiper-slide">
            <div class="academic-card">

              <img
                src="/image/IMG-20260622-WA0020.jpg"
                alt="د. عبدالناصر احمد القاضي"
                class="academic-img">

              <h3 class="text-xl md:text-2xl font-extrabold">
                د. عبدالناصر احمد القاضي
              </h3>

              <p class="text-[var(--primary)] font-bold text-sm">
                دكتوراه في تمريض الحالات الحرجة
              </p>

              <div class="stars">★★★★★</div>

              <p class="text-sm text-[var(--text-secondary)] mb-4">
                دكتوراه في تمريض الحالات الحرجة - جامعة اسيوط - مصر
              </p>

              <a href="#" class="contact-btn">
                تواصل معي ←
              </a>

            </div>
          </div>


          <!-- Academic 4 - القباني -->
          <div class="swiper-slide">
            <div class="academic-card">

              <img
                src="/image/IMG-20260813-WA0003.jpg"
                alt="أ.م.د تركي يحيى القباني"
                class="academic-img">

              <h3 class="text-xl md:text-2xl font-extrabold">
                أ.م.د تركي يحيى القباني
              </h3>

              <p class="text-[var(--primary)] font-bold text-sm">
                نائب رئيس جامعه الرازي للشؤون الأكاديمية
              </p>

              <div class="stars">★★★★★</div>

              <p class="text-sm text-[var(--text-secondary)] mb-4">

                أ.م.د/ تركي يحيى القباني هو قامة أكاديمية وإدارية بارزة، يشغل حالياً منصب نائب رئيس جامعة الرازي للشؤون الأكاديمية. يمتلك مسيرة علمية ومهنية حافلة، حيث حصل على درجة الدكتوراه في الإدارة والتخطيط من جامعة صنعاء في اليمن. تتسم خبرته بالشمولية في مجالات الإدارة الجامعية، وضمان الجودة، والتخطيط الاستراتيجي، والبحث العلمي </p>

              <a href="#" class="contact-btn">
                تواصل معي ←
              </a>

            </div>
          </div>

        </div>

        <!-- Navigation -->
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>

        <!-- Pagination -->
        <div class="swiper-pagination"></div>

      </div>


      <!-- Bottom CTA -->
      <div class="text-center mt-8 md:mt-10">

        <div class="inline-flex flex-wrap items-center justify-center gap-3 bg-[rgba(79,70,229,0.1)] px-4 md:px-8 py-3 rounded-full">

          <span class="text-2xl">🎓</span>

          <span class="text-sm md:text-base font-semibold">
            انضم إلى نخبة الأكاديميين ووسّع نطاق تأثيرك
          </span>

          <a
            href="register.php?role=academic"
            class="bg-[var(--primary)] text-white rounded-full px-5 py-2 text-sm font-bold transition hover:scale-105">
            تقديم طلب
          </a>

        </div>

      </div>

    </div>
  </section>

  <!-- How It Works -->
  <section id="how-it-works" class="py-12 md:py-20 px-4 max-w-6xl mx-auto">
    <div class="text-center mb-10 md:mb-14 fade-up">
      <span class="inline-block bg-[var(--primary-light)] text-[var(--primary)] px-4 py-1.5 rounded-full text-xs md:text-sm font-bold mb-3">خطوات بسيطة</span>
      <h2 class="text-3xl md:text-4xl font-extrabold mb-3">آلية العمل بكل بساطة</h2>
      <p class="text-[var(--text-secondary)] max-w-2xl mx-auto">ثلاث خطوات فقط تفصلك عن إنجاز بحثك باحترافية</p>
    </div>
    <div class="flex flex-col md:flex-row justify-center gap-8 md:gap-12 items-stretch">
      <div class="step-card text-center flex-1 max-w-xs mx-auto w-full fade-up" style="animation-delay:0.1s">
        <div class="step-number">1</div>
        <h4 class="font-extrabold text-xl md:text-2xl mb-2">اطلب الخدمة</h4>
        <p class="text-sm md:text-base text-[var(--text-secondary)]">حدد الخدمة وضع جميع تفاصيل الطلب والمرفقات بكل يسر.</p>
      </div>
      <div class="step-card text-center flex-1 max-w-xs mx-auto w-full fade-up" style="animation-delay:0.2s">
        <div class="step-number" style="background:rgba(245,158,11,0.1);color:var(--warning)">2</div>
        <h4 class="font-extrabold text-xl md:text-2xl mb-2">اتفق مع الأكاديمي</h4>
        <p class="text-sm md:text-base text-[var(--text-secondary)]">تلق تسعيرة واضحة وتحدث مباشرة لضمان فهم المطلوب.</p>
      </div>
      <div class="step-card text-center flex-1 max-w-xs mx-auto w-full fade-up" style="animation-delay:0.3s">
        <div class="step-number" style="background:rgba(16,185,129,0.1);color:var(--success)">3</div>
        <h4 class="font-extrabold text-xl md:text-2xl mb-2">استلم مشروعك</h4>
        <p class="text-sm md:text-base text-[var(--text-secondary)]">راجع العمل بعد الإنجاز وقيّم التجربة لضمان الحقوق للجميع.</p>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="py-10 md:py-16 px-4 bg-[var(--bg-main)] text-center border-t border-[var(--border-color)]">
    <div class="text-3xl md:text-4xl font-black text-[var(--text-primary)] mb-4">🎓 تواصل</div>
    <p class="text-[var(--text-secondary)] text-sm md:text-base mb-6">المنصة الأكاديمية المتكاملة 2026 - جميع الحقوق محفوظة.</p>
    <div class="flex justify-center gap-5 flex-wrap">
      <a href="admin/pages/dashboard.php" class="text-[var(--text-muted)] text-xs md:text-sm hover:text-[var(--primary)]">دخول الإدارة</a>
      <a href="student/student-dashboard.php" class="text-[var(--text-muted)] text-xs md:text-sm hover:text-[var(--primary)]">لوحة الطالب</a>
      <a href="academics/academic-dashboard.php" class="text-[var(--text-muted)] text-xs md:text-sm hover:text-[var(--primary)]">لوحة الأكاديمي</a>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <script src="assets/js/global.js"></script>
  <script>
    // بيانات الخدمات من قاعدة البيانات
    var allServices = <?= $all_services_json ?>;

    // إذا كانت البيانات غير مصفوفة (فارغة مثلاً) نعيّن مصفوفة فارغة لتجنب الأخطاء
    if (!Array.isArray(allServices)) {
      allServices = [];
    }

    // Swiper
    const swiper = new Swiper('.academicsSwiper', {
      slidesPerView: 'auto',
      spaceBetween: 20,
      loop: true,
      autoplay: {
        delay: 3000,
        disableOnInteraction: false,
        pauseOnMouseEnter: true
      },
      speed: 700,
      breakpoints: {
        320: {
          spaceBetween: 16
        },
        640: {
          spaceBetween: 20
        },
        1024: {
          spaceBetween: 24
        },
        1280: {
          spaceBetween: 28
        }
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
        dynamicBullets: true
      },
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev'
      },
      grabCursor: true,
    });

    // القائمة المتنقلة
    const menuToggle = document.getElementById('menuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    const overlay = document.getElementById('menuOverlay');

    function closeMenu() {
      mobileMenu.classList.remove('active');
      overlay.classList.remove('active');
      document.body.style.overflow = '';
    }

    function openMenu() {
      mobileMenu.classList.add('active');
      overlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
    if (menuToggle) menuToggle.addEventListener('click', () => {
      if (mobileMenu.classList.contains('active')) closeMenu();
      else openMenu();
    });
    if (overlay) overlay.addEventListener('click', closeMenu);
    document.querySelectorAll('.mobile-menu a').forEach(link => link.addEventListener('click', closeMenu));

    // تأثير الظهور عند التمرير
    const fadeElements = document.querySelectorAll('.fade-up');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.animationPlayState = 'running';
          observer.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.1
    });
    fadeElements.forEach(el => {
      el.style.animationPlayState = 'paused';
      observer.observe(el);
    });

    window.addEventListener('resize', () => swiper.update());

    // ── وظيفة عرض / إخفاء جميع الخدمات ──
    let servicesLoaded = false;

    const toggleBtn = document.getElementById('toggleAllServices');
    const container = document.getElementById('allServices');
    const grid = document.getElementById('allServicesGrid');

    function toggleServices() {
      if (!container) return;

      if (container.classList.contains('open')) {
        container.classList.remove('open');
        if (toggleBtn) toggleBtn.innerHTML = '📋 كل الخدمات';
      } else {
        if (!servicesLoaded) {
          renderAllServices();
          servicesLoaded = true;
        }
        container.classList.add('open');
        if (toggleBtn) toggleBtn.innerHTML = '✕ إخفاء الخدمات';
      }
    }

    function renderAllServices() {
      if (!grid) return;

      if (!allServices.length) {
        grid.innerHTML = `
        <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-secondary);">
          ⚠️ لا توجد خدمات متاحة حالياً.
        </div>`;
        return;
      }

      grid.innerHTML = allServices.map((s, i) => `
      <div class="feature-card" style="animation: fadeUp 0.6s forwards; animation-delay:${i * 0.08}s; opacity:0;">
        <div class="feature-icon">${s.icon || '📦'}</div>
        <h3 class="text-xl md:text-2xl font-extrabold mb-2">${s.name || 'خدمة'}</h3>
        <p class="text-sm md:text-base text-[var(--text-secondary)] leading-relaxed">${s.description || 'خدمة أكاديمية احترافية.'}</p>
      </div>
    `).join('');
    }

    // ربط الزر
    document.addEventListener('DOMContentLoaded', function() {
      if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleServices);
      }
    });
  </script>
</body>

</html>