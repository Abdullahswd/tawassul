<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>التقييمات والآراء - Eduroad</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/global.css">
  <style>
    .reviews-header {
      background: linear-gradient(145deg, var(--bg-main) 0%, rgba(79, 70, 229, 0.05) 100%);
      border-radius: 48px;
      padding: 48px 24px;
      margin-bottom: 48px;
      text-align: center;
    }
    .stat-card {
      background: var(--bg-card);
      border-radius: 32px;
      padding: 24px;
      transition: all 0.3s ease;
      border: 1px solid var(--border-color);
    }
    .stat-card:hover {
      transform: translateY(-5px);
      border-color: var(--primary);
      box-shadow: 0 15px 30px -12px rgba(79, 70, 229, 0.15);
    }
    .review-card {
      background: var(--bg-card);
      border-radius: 32px;
      padding: 28px;
      transition: all 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
      border: 1px solid var(--border-color);
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    .review-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.15);
      border-color: var(--primary);
    }
    .avatar {
      width: 48px;
      height: 48px;
      border-radius: 28px;
      background: var(--primary-light);
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 18px;
    }
    .verified-badge {
      background: rgba(16, 185, 129, 0.12);
      color: #10b981;
      padding: 2px 8px;
      border-radius: 40px;
      font-size: 11px;
      font-weight: 700;
    }
    .stars {
      direction: ltr;
      color: #fbbf24;
      letter-spacing: 2px;
      font-size: 16px;
    }
    .divider-dashed {
      border-top: 1px dashed var(--border-color);
    }
    .fade-up {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.8s ease, transform 0.8s ease;
    }
  </style>
</head>
<body style="background:var(--bg-main)">

  <nav class="public-nav">
    <div style="display:flex;align-items:center;gap:12px;font-size:24px;font-weight:900;color:var(--primary)">
      <img src="image/eduroad_logo.png" alt="Eduroad" style="height:28px;width:auto" /> <span style="color:var(--text-primary)">Eduroad</span>
    </div>
    <div class="nav-links hidden md:flex">
      <a href="index.php#features">المميزات</a>
      <a href="index.php#how-it-works">كيف نعمل</a>
      <a href="index.php#academics">أكاديميونا</a>
      <a href="packages.php">الباقات</a>
      <a href="reviews.php">التقييمات</a>
    </div>
    <div style="display:flex;gap:12px">
      <button class="dark-toggle" style="background:none;border:none;font-size:20px;cursor:pointer">🌙</button>
      <a href="login.php" class="btn btn-outline" style="padding:8px 16px;font-size:14px">دخول</a>
      <a href="register.php" class="btn btn-primary" style="padding:8px 16px;font-size:14px">إنشاء حساب</a>
    </div>
  </nav>

  <div style="padding:60px 20px;max-width:1200px;margin:0 auto">

    <!-- Header محسن مثل الباقات -->
    <div class="reviews-header fade-up">
      <span style="display:inline-block;background:var(--primary-light);color:var(--primary);padding:6px 18px;border-radius:40px;font-size:13px;font-weight:700;margin-bottom:20px">آراء حقيقية وموثقة</span>
      <h1 style="font-size:44px;font-weight:900;margin-bottom:20px;background: linear-gradient(135deg, var(--primary), #dc5ab1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
        ماذا يقول طلابنا؟
      </h1>
      <p style="font-size:18px;color:var(--text-secondary);max-width:700px;margin:0 auto;line-height:1.8">
        آلاف الطلاب والباحثين وثقوا تجاربهم مع أكاديميينا. هذه آراء حقيقية تعكس الجودة والالتزام.
      </p>
    </div>

    <!-- بطاقات الإحصائيات (مثل قسم الباقات) -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:24px;margin-bottom:60px" class="fade-up">
      <div class="stat-card" style="text-align:center">
        <div style="font-size:48px;font-weight:900;color:var(--primary)">4.9</div>
        <div class="stars" style="font-size:18px;margin:8px 0">★★★★★</div>
        <div style="font-size:14px;color:var(--text-secondary)">متوسط التقييم العام</div>
      </div>
      <div class="stat-card" style="text-align:center">
        <div style="font-size:48px;font-weight:900;color:var(--text-primary)">15K+</div>
        <div style="font-size:14px;color:var(--text-secondary);margin-top:8px">مراجعة معتمدة وموثقة</div>
      </div>
      <div class="stat-card" style="text-align:center">
        <div style="font-size:48px;font-weight:900;color:#10b981">98%</div>
        <div style="font-size:14px;color:var(--text-secondary);margin-top:8px">نسبة الرضا والإنجاز</div>
      </div>
    </div>

    <!-- شبكة التقييمات -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(380px, 1fr));gap:28px">
      
      <!-- تقييم 1 -->
      <div class="review-card fade-up" style="animation-delay:0.1s">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
          <div style="display:flex;align-items:center;gap:14px">
            <div class="avatar">أ</div>
            <div>
              <div style="font-weight:800;font-size:16px;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                أحمد عبدالله
                <span class="verified-badge">✓ موثق</span>
              </div>
              <div style="font-size:12px;color:var(--text-muted)">طالب ماجستير إحصاء</div>
            </div>
          </div>
          <div style="font-size:12px;color:var(--text-muted)">قبل 3 أيام</div>
        </div>
        <div class="stars" style="margin-bottom:14px">★★★★★</div>
        <h4 style="font-weight:800;margin-bottom:12px;font-size:18px">احترافية في التحليل الإحصائي</h4>
        <p style="font-size:14px;color:var(--text-secondary);line-height:1.7;flex-grow:1">
          عملت مع د. محمد سعيد على تحليل استبيانات رسالة الماجستير. كان العمل منظماً ودقيقاً، والتزامه بالمواعيد ممتاز. أنصح به بشدة.
        </p>
        <div class="divider-dashed" style="margin:20px 0 12px"></div>
        <div style="font-size:13px;color:var(--text-secondary)">
          <span style="font-weight:600">الأكاديمي:</span>
          <a href="#" style="color:var(--primary);font-weight:700"> د. محمد سعيد</a>
        </div>
      </div>

      <!-- تقييم 2 -->
      <div class="review-card fade-up" style="animation-delay:0.2s">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
          <div style="display:flex;align-items:center;gap:14px">
            <div class="avatar">س</div>
            <div>
              <div style="font-weight:800;font-size:16px;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                سارة محمد
                <span class="verified-badge">✓ موثق</span>
              </div>
              <div style="font-size:12px;color:var(--text-muted)">طالبة دكتوراه تاريخ</div>
            </div>
          </div>
          <div style="font-size:12px;color:var(--text-muted)">قبل أسبوع</div>
        </div>
        <div class="stars" style="margin-bottom:14px">★★★★★</div>
        <h4 style="font-weight:800;margin-bottom:12px;font-size:18px">تدقيق لغوي استثنائي</h4>
        <p style="font-size:14px;color:var(--text-secondary);line-height:1.7;flex-grow:1">
          الأستاذ فهد قام بمراجعة شاملة للغة في بحثي، اكتشف أخطاء إملائية وتركيبية لم أكن منتبهاً لها. العمل احترافي جداً وأنصح به.
        </p>
        <div class="divider-dashed" style="margin:20px 0 12px"></div>
        <div style="font-size:13px;color:var(--text-secondary)">
          <span style="font-weight:600">الأكاديمي:</span>
          <a href="#" style="color:var(--primary);font-weight:700"> أ. فهد عبدالله</a>
        </div>
      </div>

      <!-- تقييم 3 (يمكن إضافته لاحقاً من قاعدة البيانات) -->
      <div class="review-card fade-up" style="animation-delay:0.3s">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
          <div style="display:flex;align-items:center;gap:14px">
            <div class="avatar">ن</div>
            <div>
              <div style="font-weight:800;font-size:16px;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                نورة خالد
                <span class="verified-badge">✓ موثق</span>
              </div>
              <div style="font-size:12px;color:var(--text-muted)">باحثة دكتوراه</div>
            </div>
          </div>
          <div style="font-size:12px;color:var(--text-muted)">قبل أسبوعين</div>
        </div>
        <div class="stars" style="margin-bottom:14px">★★★★★</div>
        <h4 style="font-weight:800;margin-bottom:12px;font-size:18px">باقة النخبة كانت خياراً موفقاً</h4>
        <p style="font-size:14px;color:var(--text-secondary);line-height:1.7;flex-grow:1">
          استخدمت باقة VIP وشعرت بفرق كبير. الدعم مستمر، والأكاديميون يردون على استفساراتي حتى خارج أوقات العمل. شكراً تواصل.
        </p>
        <div class="divider-dashed" style="margin:20px 0 12px"></div>
        <div style="font-size:13px;color:var(--text-secondary)">
          <span style="font-weight:600">الأكاديمي:</span>
          <a href="#" style="color:var(--primary);font-weight:700"> د. هاني الجدعاني</a>
        </div>
      </div>

    </div>

    <!-- قسم إضافي للحث على كتابة تقييم -->
    <div style="margin-top:80px;background:linear-gradient(145deg, var(--bg-card), rgba(79, 70, 229, 0.03));border:1px solid var(--border-color);border-radius:48px;padding:48px 24px;text-align:center" class="fade-up">
      <h4 style="font-size:24px;font-weight:800;margin-bottom:16px">📣 صوتك يهمنا</h4>
      <p style="color:var(--text-secondary);font-size:16px;max-width:700px;margin:0 auto 24px">
        هل استفدت من خدماتنا؟ شارك تجربتك مع الآخرين وساهم في بناء مجتمع أكاديمي شفاف.
      </p>
      <a href="write-review.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px">اكتب تقييمك الآن ✍️</a>
    </div>

  </div>

  <script src="assets/js/global.js"></script>
  <script>
    // تفعيل تأثير الظهور التدريجي
    const fadeElements = document.querySelectorAll('.fade-up');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });
    fadeElements.forEach(el => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(30px)';
      observer.observe(el);
    });
  </script>
</body>
</html>