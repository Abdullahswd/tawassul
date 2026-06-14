<?php
require_once __DIR__ . '/config/auth.php';
if (isLoggedIn()) {
    session_unset();
    session_destroy();
    // Restart a clean empty session
    session_start();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>تواصل - منصة الخدمات الأكاديمية الشاملة</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <!-- Swiper CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <link rel="stylesheet" href="assets/css/global.css">
  <style>
    /* Hero Section محسّن */
    .hero {
      background: linear-gradient(145deg, var(--bg-main) 0%, rgba(79, 70, 229, 0.05) 100%);
      border-radius: 48px;
      padding: 80px 20px;
      margin: 20px 20px 0 20px;
      text-align: center;
    }

    /* بطاقات الخدمات بنفس نمط الباقات */
    .feature-card {
      background: var(--bg-card);
      border-radius: 32px;
      padding: 32px 24px;
      text-align: center;
      transition: all 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
      border: 1px solid var(--border-color);
      height: 100%;
    }
    .feature-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 25px 40px -12px rgba(79, 70, 229, 0.2);
      border-color: var(--primary);
    }
    .feature-icon {
      width: 80px;
      height: 80px;
      background: var(--primary-light);
      border-radius: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 40px;
      margin: 0 auto 24px;
      transition: all 0.3s;
    }
    .feature-card:hover .feature-icon {
      background: var(--primary);
      color: white;
      transform: scale(1.05);
    }

    /* قسم الأكاديميين - كاروسيل */
    .academics-section {
      background: linear-gradient(145deg, var(--bg-main) 0%, rgba(79, 70, 229, 0.02) 100%);
      border-radius: 48px;
      margin: 40px 20px;
      padding: 60px 20px;
    }
    .academic-card {
      background: var(--bg-card);
      border-radius: 32px;
      padding: 28px 20px;
      text-align: center;
      transition: all 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
      box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.1);
      border: 1px solid var(--border-color);
      margin: 15px 0;
    }
    .academic-card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 30px 45px -12px rgba(79, 70, 229, 0.25);
      border-color: var(--primary);
    }
    .academic-img {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      margin: 0 auto 18px;
      border: 4px solid var(--primary-light);
      transition: all 0.4s ease;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
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
      padding: 10px 28px;
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

    /* قسم آلية العمل */
    .step-number {
      width: 80px;
      height: 80px;
      background: var(--primary-light);
      color: var(--primary);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
      font-weight: 900;
      margin: 0 auto 16px;
      transition: all 0.3s;
    }
    .step-card:hover .step-number {
      background: var(--primary);
      color: white;
      transform: scale(1.05);
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

    /* Swiper buttons */
    .swiper-button-next, .swiper-button-prev {
      color: var(--primary);
      background: rgba(255,255,255,0.7);
      backdrop-filter: blur(8px);
      width: 44px;
      height: 44px;
      border-radius: 50%;
      transition: all 0.3s;
    }
    .swiper-button-next:after, .swiper-button-prev:after {
      font-size: 20px;
      font-weight: bold;
    }
    .swiper-button-next:hover, .swiper-button-prev:hover {
      background: var(--primary);
      color: white;
      transform: scale(1.1);
    }
    .swiper-pagination-bullet-active {
      background: var(--primary) !important;
    }

    /* fade-up animation */
    .fade-up {
      opacity: 0;
      transform: translateY(30px);
      animation: fadeUp 0.8s forwards;
    }
    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <nav class="public-nav">
    <div style="display:flex;align-items:center;gap:12px;font-size:24px;font-weight:900;color:var(--primary)">
      🎓 <span style="color:var(--text-primary)">تواصل</span>
    </div>
    <div class="nav-links hidden md:flex">
      <a href="#features">المميزات</a>
      <a href="#how-it-works">كيف نعمل</a>
      <a href="#academics">أكاديميونا</a>
      <a href="packages.php">الباقات</a>
      <a href="reviews.php">التقييمات</a>
    </div>
    <div style="display:flex;gap:12px">
      <button class="dark-toggle" style="background:none;border:none;font-size:20px;cursor:pointer">🌙</button>
      <a href="login.php" class="btn btn-outline" style="padding:8px 16px;font-size:14px">دخول</a>
      <a href="register.php" class="btn btn-primary" style="padding:8px 16px;font-size:14px">إنشاء حساب</a>
    </div>
  </nav>

  <!-- Hero Section محسّن -->
  <header class="hero fade-up">
    <div style="max-width:800px;margin:0 auto">
      <span style="display:inline-block;padding:8px 20px;background:var(--primary-light);color:var(--primary);border-radius:40px;font-weight:800;font-size:14px;margin-bottom:24px">🚀 المنصة الأكاديمية رقم 1 في الوطن العربي</span>
      <h1 style="font-size:52px;font-weight:900;margin-bottom:24px;line-height:1.3;background: linear-gradient(135deg, var(--text-primary), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">أنجز أبحاثك ومشاريعك الجامعية بكفاءة واحترافية</h1>
      <p style="font-size:18px;color:var(--text-secondary);margin-bottom:40px;line-height:1.8">نربط الطلاب بنخبة من الأكاديميين المتخصصين لتقديم الدعم والمساندة في كافة متطلبات التحليل الإحصائي، الترجمة، التدقيق اللغوي، والمزيد الموثق.</p>
      <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap">
        <a href="register.php" class="btn btn-primary" style="font-size:18px;padding:16px 32px">ابدأ الآن - مجاناً 🚀</a>
        <a href="#features" class="btn btn-outline" style="font-size:18px;padding:16px 32px">📋 تصفح الخدمات المتوفرة</a>
      </div>
      <p style="font-size:14px; color:var(--text-muted); margin-top:32px">🔒 خدمات موثقة بضمان الجودة | أكثر من 15,000 طالب استفادوا</p>
    </div>
  </header>

  <!-- Services Highlights (بطاقات محسّنة) -->
  <section id="features" style="padding:80px 20px;max-width:1200px;margin:0 auto">
    <div style="text-align:center;margin-bottom:48px" class="fade-up">
      <span style="display:inline-block;background:var(--primary-light);color:var(--primary);padding:6px 18px;border-radius:40px;font-size:13px;font-weight:700;margin-bottom:16px">خدماتنا المتكاملة</span>
      <h2 style="font-size:36px;font-weight:800;margin-bottom:16px">كل ما تحتاجه كطالب ودكتور في مكان واحد</h2>
      <p style="color:var(--text-secondary);max-width:600px;margin:0 auto">نقدم مجموعة شاملة من الخدمات الأكاديمية لمساعدتك في كل مرحلة من مراحل بحثك العلمي</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:28px">
      <div class="feature-card fade-up" style="animation-delay:0.1s">
        <div class="feature-icon">🏗️</div>
        <h3 style="font-size:22px;font-weight:800;margin-bottom:12px">التأسيس الأكاديمي</h3>
        <p style="color:var(--text-secondary);font-size:14px;line-height:1.6">نساعدك في اختيار عنوان البحث، صياغة المقترح (Proposal)، وبناء خطة بحثية متكاملة وقوية.</p>
      </div>
      <div class="feature-card fade-up" style="animation-delay:0.2s">
        <div class="feature-icon">📝</div>
        <h3 style="font-size:22px;font-weight:800;margin-bottom:12px">تطوير البحث العلمي</h3>
        <p style="color:var(--text-secondary);font-size:14px;line-height:1.6">كتابة الإطار النظري، تلخيص الدراسات السابقة، وإعادة الصياغة الأكاديمية باحترافية عالية.</p>
      </div>
      <div class="feature-card fade-up" style="animation-delay:0.3s">
        <div class="feature-icon">✏️</div>
        <h3 style="font-size:22px;font-weight:800;margin-bottom:12px">التدقيق والتحرير</h3>
        <p style="color:var(--text-secondary);font-size:14px;line-height:1.6">تدقيق لغوي ونحوي شامل، وتنسيق الأبحاث حسب أدلة الجامعات المحلية والدولية.</p>
      </div>
      <div class="feature-card fade-up" style="animation-delay:0.4s">
        <div class="feature-icon">📊</div>
        <h3 style="font-size:22px;font-weight:800;margin-bottom:12px">التحليل الإحصائي</h3>
        <p style="color:var(--text-secondary);font-size:14px;line-height:1.6">تحليل البيانات باستخدام SPSS، اختبار الفرضيات، وتفسير النتائج بدقة وموثوقية.</p>
      </div>
      <div class="feature-card fade-up" style="animation-delay:0.5s">
        <div class="feature-icon">🌐</div>
        <h3 style="font-size:22px;font-weight:800;margin-bottom:12px">الترجمة الأكاديمية</h3>
        <p style="color:var(--text-secondary);font-size:14px;line-height:1.6">ترجمة معتمدة للأبحاث والمصطلحات العلمية من وإلى اللغة الإنجليزية بجودة أكاديمية.</p>
      </div>
      <div class="feature-card fade-up" style="animation-delay:0.6s">
        <div class="feature-icon">📰</div>
        <h3 style="font-size:22px;font-weight:800;margin-bottom:12px">النشر العلمي</h3>
        <p style="color:var(--text-secondary);font-size:14px;line-height:1.6">المساعدة في تجهيز الأبحاث للنشر في المجلات المحكمة ذات التأثير العالي (ISI/Scopus).</p>
      </div>
    </div>
  </section>

  <!-- أكاديميونا (كاروسيل محسّن) -->
  <section id="academics" class="academics-section">
    <div style="max-width:1300px;margin:0 auto">
      <div style="text-align:center;margin-bottom:48px" class="fade-up">
        <span style="display:inline-block;padding:6px 14px;background:var(--primary-light);color:var(--primary);border-radius:40px;font-size:13px;font-weight:700;margin-bottom:16px">نخبة من الخبراء</span>
        <h2 style="font-size:36px;font-weight:800;margin-bottom:16px">أكاديميونا المتميزون</h2>
        <p style="color:var(--text-secondary);max-width:600px;margin:0 auto">تعاون مع أفضل الأساتذة والباحثين – اختر من بين خبرائنا المتخصصين</p>
      </div>

      <div class="swiper academicsSwiper" style="overflow: visible; padding: 15px 5px 40px;">
        <div class="swiper-wrapper">
          <div class="swiper-slide"><div class="academic-card"><img src="https://randomuser.me/api/portraits/men/54.jpg" alt="د. أحمد الكبسي" class="academic-img"><h3 style="font-size:22px;font-weight:800">د. أحمد الكبسي</h3><p style="color:var(--primary);font-weight:700;font-size:14px">أستاذ الإحصاء وتحليل البيانات</p><div class="stars">★★★★★</div><p style="font-size:14px;color:var(--text-secondary);margin-bottom:20px">متخصص في التحليل الإحصائي باستخدام SPSS وAMOS.</p><a href="#" class="contact-btn">تواصل معي ←</a></div></div>
          <div class="swiper-slide"><div class="academic-card"><img src="https://randomuser.me/api/portraits/men/45.jpg" alt="د. عبدالرؤوف الآنسي" class="academic-img"><h3 style="font-size:22px;font-weight:800">د. عبدالرؤوف الآنسي</h3><p style="color:var(--primary);font-weight:700;font-size:14px">خبير المناهج والترجمة</p><div class="stars">★★★★★</div><p style="font-size:14px;color:var(--text-secondary);margin-bottom:20px">متخصص في الترجمة الأكاديمية وتدقيق الأبحاث.</p><a href="#" class="contact-btn">تواصل معي ←</a></div></div>
          <div class="swiper-slide"><div class="academic-card"><img src="https://randomuser.me/api/portraits/women/52.jpg" alt="د. أروى الشرجبي" class="academic-img"><h3 style="font-size:22px;font-weight:800">د. أروى الشرجبي</h3><p style="color:var(--primary);font-weight:700;font-size:14px">أستاذ الأدب والنقد</p><div class="stars">★★★★★</div><p style="font-size:14px;color:var(--text-secondary);margin-bottom:20px">متخصصة في التحرير اللغوي والتدقيق الأكاديمي.</p><a href="#" class="contact-btn">تواصل معي ←</a></div></div>
          <div class="swiper-slide"><div class="academic-card"><img src="https://randomuser.me/api/portraits/men/67.jpg" alt="د. محمد المخلافي" class="academic-img"><h3 style="font-size:22px;font-weight:800">د. محمد المخلافي</h3><p style="color:var(--primary);font-weight:700;font-size:14px">خبير النشر العلمي</p><div class="stars">★★★★★</div><p style="font-size:14px;color:var(--text-secondary);margin-bottom:20px">خبرة واسعة في تجهيز الأبحاث للنشر في المجلات المحكمة.</p><a href="#" class="contact-btn">تواصل معي ←</a></div></div>
          <div class="swiper-slide"><div class="academic-card"><img src="https://randomuser.me/api/portraits/women/44.jpg" alt="د. إيمان المتوكل" class="academic-img"><h3 style="font-size:22px;font-weight:800">د. إيمان المتوكل</h3><p style="color:var(--primary);font-weight:700;font-size:14px">أستاذ إدارة الأعمال</p><div class="stars">★★★★★</div><p style="font-size:14px;color:var(--text-secondary);margin-bottom:20px">متخصصة في دراسات الجدوى وخطط الأعمال.</p><a href="#" class="contact-btn">تواصل معي ←</a></div></div>
        </div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-pagination"></div>
      </div>

      <div style="text-align:center; margin-top: 30px;">
        <div style="display:inline-flex;align-items:center;gap:12px;background:rgba(79,70,229,0.1);padding:12px 28px;border-radius:60px;backdrop-filter:blur(4px)">
          <span style="font-size:24px">🎓</span>
          <span style="font-weight:600">انضم إلى نخبة الأكاديميين ووسّع نطاق تأثيرك</span>
          <a href="register.php?role=academic" style="background:var(--primary);color:white;border-radius:40px;padding:8px 24px;font-weight:700;transition:0.3s">تقديم طلب</a>
        </div>
      </div>
    </div>
  </section>

  <!-- How It Works (محسّن) -->
  <section id="how-it-works" style="padding:80px 20px;max-width:1200px;margin:0 auto">
    <div style="text-align:center;margin-bottom:48px" class="fade-up">
      <span style="display:inline-block;background:var(--primary-light);color:var(--primary);padding:6px 18px;border-radius:40px;font-size:13px;font-weight:700;margin-bottom:16px">خطوات بسيطة</span>
      <h2 style="font-size:36px;font-weight:800;margin-bottom:16px">آلية العمل بكل بساطة</h2>
      <p style="color:var(--text-secondary);max-width:600px;margin:0 auto">ثلاث خطوات فقط تفصلك عن إنجاز بحثك باحترافية</p>
    </div>
    <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:40px">
      <div class="step-card fade-up" style="animation-delay:0.1s; text-align:center">
        <div class="step-number">1</div>
        <h4 style="font-weight:800;font-size:20px;margin-bottom:12px">اطلب الخدمة</h4>
        <p style="font-size:15px;color:var(--text-secondary)">حدد الخدمة وضع جميع تفاصيل الطلب والمرفقات بكل يسر.</p>
      </div>
      <div class="step-card fade-up" style="animation-delay:0.2s; text-align:center">
        <div class="step-number" style="background:rgba(245,158,11,0.1);color:var(--warning)">2</div>
        <h4 style="font-weight:800;font-size:20px;margin-bottom:12px">اتفق مع الأكاديمي</h4>
        <p style="font-size:15px;color:var(--text-secondary)">تلق تسعيرة واضحة وتحدث مباشرة لضمان فهم المطلوب.</p>
      </div>
      <div class="step-card fade-up" style="animation-delay:0.3s; text-align:center">
        <div class="step-number" style="background:rgba(16,185,129,0.1);color:var(--success)">3</div>
        <h4 style="font-weight:800;font-size:20px;margin-bottom:12px">استلم مشروعك</h4>
        <p style="font-size:15px;color:var(--text-secondary)">راجع العمل بعد الإنجاز وقيّم التجربة لضمان الحقوق للجميع.</p>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer style="padding:60px 20px;background:var(--bg-main);text-align:center;border-top:1px solid var(--border-color)">
    <div style="font-size:28px;font-weight:900;color:var(--text-primary);margin-bottom:20px">🎓 تواصل</div>
    <p style="color:var(--text-secondary);margin-bottom:24px">المنصة الأكاديمية المتكاملة 2026 - جميع الحقوق محفوظة.</p>
    <div style="display:flex;justify-content:center;gap:20px;flex-wrap:wrap">
      <a href="admin/pages/dashboard.php" style="color:var(--text-muted);font-size:14px">دخول الإدارة</a>
      <a href="student/student-dashboard.php" style="color:var(--text-muted);font-size:14px">لوحة الطالب</a>
      <a href="academics/academic-dashboard.php" style="color:var(--text-muted);font-size:14px">لوحة الأكاديمي</a>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <script src="assets/js/global.js"></script>
  <script>
    const swiper = new Swiper('.academicsSwiper', {
      slidesPerView: 1,
      spaceBetween: 24,
      loop: true,
      autoplay: { delay: 2500, disableOnInteraction: false, pauseOnMouseEnter: true },
      speed: 800,
      breakpoints: { 640: { slidesPerView: 2 }, 1024: { slidesPerView: 3 }, 1280: { slidesPerView: 4 } },
      pagination: { el: '.swiper-pagination', clickable: true, dynamicBullets: true },
      navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
      grabCursor: true,
    });

    // تفعيل fade-up عند التمرير للعناصر التي لم تظهر بعد
    const fadeElements = document.querySelectorAll('.fade-up');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.animationPlayState = 'running';
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });
    fadeElements.forEach(el => {
      el.style.animationPlayState = 'paused';
      observer.observe(el);
    });
  </script>
</body>
</html>