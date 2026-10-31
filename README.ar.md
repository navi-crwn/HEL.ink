<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh.md">简体中文</a> · <a href="README.es.md">Español</a> · <b>العربية</b> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.ja.md">日本語</a> · <a href="README.id.md">Bahasa Indonesia</a></p>

# HEL.ink — HEL.ink · مركز عائلة HOP و HOP ID

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="HEL.ink · مركز عائلة HOP" width="100%"></picture></p>

<p align="center"><b>HEL.ink هي بوابة الحسابات ودليل العائلة لتطبيقات HOP. سجّل الدخول مرة واحدة عبر HOP ID وتنقّل بسلاسة بين LinkHop وBioHop وPixelHop وNoteHop.</b></p>

<p align="center">
  <a href="https://hel.ink">hel.ink</a> ·
  <a href="https://hub.hel.ink">hub.hel.ink</a> ·
  <a href="#run">تشغيل نسختك</a> ·
  <a href="#features">الميزات</a> ·
  <a href="#faq">الأسئلة الشائعة</a> ·
  <a href="#family">عائلة HOP</a>
</p>

---

## مركز العائلة وبوابة الحسابات

بوابة الحسابات المركزية، تسجيل الدخول الموحد HOP ID، ودليل عائلة LinkHop وBioHop وPixelHop وNoteHop.

<a id="features"></a>

## الميزات الرئيسية

- **تسجيل الدخول الموحد (HOP ID)**: تسجيل دخول واحد يشمل جميع تطبيقات HOP مع تذاكر موقّعة وتسجيل خروج متسلسل.
- **مشغّل التطبيقات الموحد**: تنقّل بين LinkHop وBioHop وPixelHop وNoteHop بنقرة واحدة من أي مكان.
- **بنية قاعدة بيانات مشتركة**: قاعدة بيانات MySQL أو SQLite واحدة تحفظ الحسابات مع جداول هجرة مستقلة لكل تطبيق.
- **أمان قوي للحسابات**: تشفير Argon2id، ومصادقة ثنائية TOTP، وحماية Turnstile، والتحقق من البريد الإلكتروني.
- **مراقبة حية للخدمات**: فحوصات دورية للسلامة والكمون عبر جميع تطبيقات العائلة دون كشف الأسرار.
- **أصول العائلة المركزية**: الشعارات الرسمية والخطوط والبطاقات العائمة ثلاثية الأبعاد تُقدّم من hel.ink مع تجزئات SRI.
- **رموز واجهة برمجة دقيقة**: إنشاء رموز خدمة محددة الصلاحيات للتواصل بين التطبيقات والأتمتة.
- **استضافة ذاتية بالكامل**: مجاني ومفتوح المصدر 100٪. شغّل المركز وحده أو استضف بيئة HOP كاملة على خادم VPS واحد.

<a id="run"></a>

## شغّل المركز بنفسك

يعمل المركز على بنية تحتية قياسية وحديثة لـ PHP.

```bash
git clone https://github.com/navi-crwn/HEL.ink.git
cd HEL.ink
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

<a id="faq"></a>

## الأسئلة المتكررة

**ما هو HOP ID؟**
HOP ID هي خدمة المصادقة المركزية لعائلة HOP. يمنحك حساب واحد حق الوصول لجميع التطبيقات.

**هل يمكنني تشغيل المركز دون التطبيقات الأخرى؟**
نعم. يمكن للمركز العمل منفرداً كبوابة حسابات ودليل، لكنه يقدم أقصى فائدة مع تطبيقات العائلة.

**كيف يعمل تسجيل الخروج الموحد؟**
يؤدي تسجيل الخروج من المركز إلى إنهاء جلستك وإرسال إشارات خروج متسلسلة للتطبيقات المتصلة.

**هل HEL.ink مفتوح المصدر؟**
نعم، HEL.ink وجميع تطبيقات HOP العامة مرخصة بموجب رخصة MIT الحرة.

<a id="family"></a>

## عائلة HOP

HEL.ink هو المركز لعائلة من أدوات الويب مفتوحة المصدر التي تحترم الخصوصية.

| التطبيق | العنوان المباشر | الكود | الوظيفة |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | هذا المستودع | بوابة الحسابات ودخول HOP ID |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | اختصار الروابط ورموز QR |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | صفحات الروابط الشخصية والقوالب |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | استضافة الصور والوسائط |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | ملاحظات مشفرة على جهاز العميل |

<a id="security"></a>

## المساعدة والدعم

هل وجدت خطأ أو تحتاج مساعدة؟ افتح تذكرة عبر <a href="https://github.com/navi-crwn/HEL.ink/issues">قضايا GitHub</a>أو أرسل طلب سحب.

---

<p align="center"><sub>HEL.ink · HOP ID · جزء من عائلة HOP · <a href="https://hel.ink">hel.ink</a></sub></p>
