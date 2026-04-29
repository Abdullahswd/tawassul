/**
 * ============================================
 * Services Data Module
 * Tawassul Academic Platform
 * Updated based on 'خدمات المنصة.docx'
 * ============================================
 */

const CATEGORIES = [
  { id: 'cat-1', name: 'التأسيس الأكاديمي', icon: '🏗️' },
  { id: 'cat-2', name: 'كتابة وتطوير البحث', icon: '📝' },
  { id: 'cat-3', name: 'التدقيق والتحرير', icon: '✏️' },
  { id: 'cat-4', name: 'التوثيق والمراجع', icon: '📚' },
  { id: 'cat-5', name: 'التحليل الإحصائي', icon: '📊' },
  { id: 'cat-6', name: 'الاستبيانات وأدوات البحث', icon: '📋' },
  { id: 'cat-7', name: 'الفحص والمراجعة النهائية', icon: '🔍' },
  { id: 'cat-8', name: 'التحكيم الأكاديمي', icon: '⚖️' },
  { id: 'cat-9', name: 'العرض والتقديم', icon: '📽️' },
  { id: 'cat-10', name: 'النشر العلمي', icon: '📰' },
  { id: 'cat-11', name: 'الترجمة الأكاديمية', icon: '🌐' },
  { id: 'cat-12', name: 'الخدمات المساندة', icon: '🛠️' }
];

const SERVICES_DATA = [
  // 1. التأسيس الأكاديمي
  { id: 'srv-1', categoryId: 'cat-1', name: 'اقتراح عناوين بحثية', description: 'تقديم مقترحات بحثية مبتكرة وحديثة تتوافق مع المعايير الأكاديمية.', price: 150, duration: '2 - 4 أيام', status: 'active' },
  { id: 'srv-2', categoryId: 'cat-1', name: 'إعداد خطة البحث (البروبوزال)', description: 'صياغة خطة بحث متكاملة تشمل المشكلة، الأهداف، والمنهجية.', price: 400, duration: '5 - 7 أيام', status: 'active' },
  { id: 'srv-17', categoryId: 'cat-1', name: 'تحديد أهداف وتساؤلات الدراسة', description: 'صياغة دقيقة لأهداف البحث والأسئلة البحثية المنبثقة عنها.', price: 200, duration: '2 - 3 أيام', status: 'active' },
  
  // 2. كتابة وتطوير البحث
  { id: 'srv-3', categoryId: 'cat-2', name: 'كتابة الإطار النظري', description: 'إعداد وتأليف الإطار النظري للرسالة معتمدين على أحدث المصادر.', price: 700, duration: '10 - 15 يوم', status: 'active' },
  { id: 'srv-4', categoryId: 'cat-2', name: 'تلخيص ونقد الدراسات السابقة', description: 'مراجعة أدبية (Literature Review) بأسلوب علمي رصين.', price: 300, duration: '3 - 5 أيام', status: 'active' },
  { id: 'srv-18', categoryId: 'cat-2', name: 'إعادة صياغة أكاديمية', description: 'تحسين الأسلوب العلمي والربط بين الفقرات لتقوية الحجج.', price: 250, duration: '3 - 5 أيام', status: 'active' },

  // 3. التدقيق والتحرير
  { id: 'srv-5', categoryId: 'cat-3', name: 'التدقيق اللغوي والنحوي', description: 'مراجعة خالية من الأخطاء اللغوية والإملائية (عربي/إنجليزي).', price: 200, duration: '2 - 4 أيام', status: 'active' },
  { id: 'srv-19', categoryId: 'cat-3', name: 'تنسيق النصوص وإعادة الهيكلة', description: 'تنسيق البحث حسب معايير الجامعة وتوحيد المصطلحات.', price: 150, duration: '2 - 3 أيام', status: 'active' },

  // 4. التوثيق والمراجع
  { id: 'srv-7', categoryId: 'cat-4', name: 'توثيق المراجع (APA, MLA, Chicago)', description: 'توثيق علمي دقيق باستخدام برامج EndNote أو Mendeley.', price: 150, duration: '1 - 3 أيام', status: 'active' },

  // 5. التحليل الإحصائي
  { id: 'srv-8', categoryId: 'cat-5', name: 'التحليل الإحصائي SPSS / Excel', description: 'تنفيذ الاختبارات الإحصائية، اختبار الفرضيات، وتفسير النتائج.', price: 600, duration: '4 - 6 أيام', status: 'active' },
  { id: 'srv-20', categoryId: 'cat-5', name: 'إعداد الجداول والرسوم البيانية', description: 'تمثيل البيانات إحصائياً بشكل مرئي واحترافي.', price: 200, duration: '2 - 3 أيام', status: 'active' },

  // 6. الاستبيانات
  { id: 'srv-10', categoryId: 'cat-6', name: 'تصميم الاستبيانات والأدوات', description: 'بناء استبيان محكم ورفعه على Google Forms.', price: 200, duration: '2 - 3 أيام', status: 'active' },

  // 7. الفحص والمراجعة
  { id: 'srv-11', categoryId: 'cat-7', name: 'فحص الانتحال وتقليل الاقتباس', description: 'استخراج تقرير Turnitin والعمل على خفض نسبة الاقتباس.', price: 150, duration: '2 - 4 أيام', status: 'active' },

  // 8. التحكيم الأكاديمي
  { id: 'srv-12', categoryId: 'cat-8', name: 'تحكيم الأدوات والبحوث', description: 'عرض العمل على خبراء أكاديميين لتقييمه وتصحيح الأخطاء.', price: 300, duration: '3 - 5 أيام', status: 'active' },

  // 9. العرض والتقديم
  { id: 'srv-13', categoryId: 'cat-9', name: 'إعداد عرض المناقشة و الـ Script', description: 'تصميم PowerPoint وتجهيز نص الإلقاء والتدريب عليه.', price: 250, duration: '3 - 5 أيام', status: 'active' },

  // 10. النشر العلمي
  { id: 'srv-14', categoryId: 'cat-10', name: 'المساعدة في النشر الدولي', description: 'اختيار المجلات المناسبة (ISI/Scopus) وكتابة الـ Cover Letter.', price: 900, duration: '2 - 4 أسابيع', status: 'active' },

  // 11. الترجمة الأكاديمية
  { id: 'srv-15', categoryId: 'cat-11', name: 'الترجمة الأكاديمية المعتمدة', description: 'ترجمة عربية ↔ إنجليزية للمصطلحات والرسائل العلمية.', price: 100, duration: 'حسب الحجم', status: 'active' },

  // 12. الخدمات المساندة
  { id: 'srv-16', categoryId: 'cat-12', name: 'كتابة CV و SOP أكاديمي', description: 'إعداد ملفات التقديم للجامعات والمنح الدراسية.', price: 200, duration: '3 - 5 أيام', status: 'active' },
  { id: 'srv-21', categoryId: 'cat-12', name: 'استشارات أكاديمية وإدارية', description: 'تخطيط المسار البحثي وحل المعوقات الإدارية.', price: 150, duration: 'ساعة برمجية', status: 'active' }
];

const PACKAGES_DATA = [
  {
    id: 'pkg-1',
    name: 'باقة البداية',
    tagline: 'للباحثين في مرحلة التأسيس',
    price: 199,
    features: ['اختيار عنوان البحث', 'إعداد خطة البحث (Proposal)', 'استشارة أكاديمية'],
    color: 'emerald'
  },
  {
    id: 'pkg-2',
    name: 'باقة التطوير',
    tagline: 'لتحسين جودة الكتابة واللغة',
    price: 399,
    features: ['إعادة صياغة أكاديمية', 'تدقيق لغوي ونحوي', 'توثيق المراجع'],
    color: 'blue'
  },
  {
    id: 'pkg-3',
    name: 'باقة التحليل',
    tagline: 'لمرحلة معالجة البيانات',
    price: 599,
    features: ['التحليل الإحصائي SPSS', 'تفسير النتائج', 'إعداد الجداول والرسوم'],
    color: 'amber'
  },
  {
    id: 'pkg-4',
    name: 'باقة التسليم',
    tagline: 'المراجعة النهائية قبل المناقشة',
    price: 899,
    features: ['مراجعة شاملة للبحث', 'فحص وتقليل الاقتباس', 'تنسيق نهائي حسب الجامعة'],
    color: 'rose'
  },
  {
    id: 'pkg-5',
    name: 'باقة النخبة (Premium)',
    tagline: 'الدعم الكامل حتى اجتياز المناقشة',
    price: 2499,
    features: ['جميع مميزات الباقات السابقة', 'تحكيم الأدوات والبحث', 'تدريب على المناقشة والـ Script'],
    color: 'indigo'
  }
];

const MOCK_ORDERS = [
  { id: '#ORD-9021', serviceName: 'التحليل الإحصائي SPSS', status: 'new', date: '2026-04-20', total: 600 },
  { id: '#ORD-9022', serviceName: 'كتابة الإطار النظري', status: 'in_progress', date: '2026-04-18', total: 700 },
  { id: '#ORD-9023', serviceName: 'تصميم الاستبيانات', status: 'completed', date: '2026-04-10', total: 200 },
];

window.TawassulServices = {
  CATEGORIES,
  SERVICES_DATA,
  PACKAGES_DATA,
  MOCK_ORDERS,
  getServiceById: (id) => SERVICES_DATA.find(s => s.id === id),
  getCategoryById: (id) => CATEGORIES.find(c => c.id === id),
  getServicesByCategory: (catId) => SERVICES_DATA.filter(s => s.categoryId === catId)
};
