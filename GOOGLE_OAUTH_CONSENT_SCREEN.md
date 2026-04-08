# Google OAuth 2.0 Setup - Step-by-Step Visual Guide

## Current Step: Configure OAuth Consent Screen

You're on the **"Créer un ID client OAuth"** (Create OAuth Client ID) page.

---

## ✅ What You Need to Fill In

### 1. Origines JavaScript autorisées (JavaScript Origins)

**What is this?**
- Specifies which websites can call Google APIs directly from JavaScript in a browser

**What to enter:**
```
http://localhost:8000
```

**How:**
1. Click **"+ Ajouter un URI"** (Add URI) button
2. Paste: `http://localhost:8000`
3. Press Enter or click outside to confirm

---

### 2. URI de redirection autorisés (Authorized Redirect URIs)

**What is this?**
- The URL where Google redirects users after they authorize your app
- This is where your app handles the OAuth callback

**What to enter:**
```
http://localhost:8000/connect/google/check
```

**How:**
1. Click **"+ Ajouter un URI"** (Add URI) button (the second one)
2. Paste: `http://localhost:8000/connect/google/check`
3. Press Enter or click outside to confirm

---

## 📋 Values Summary

Copy these exact values:

```
JavaScript Origins:
http://localhost:8000

Redirect URIs:
http://localhost:8000/connect/google/check
```

---

## 🎯 After Filling In

1. **Check both fields are filled** ✓
2. **Click "Créer"** (Create) button at bottom
3. **Wait for credentials dialog** to appear
4. **Copy your credentials:**
   - Client ID (long string ending in .apps.googleusercontent.com)
   - Client Secret (starts with GOCSPX-)

---

## 📝 Save Your Credentials

In `.env.local`, replace:

```env
OAUTH_GOOGLE_CLIENT_ID=YOUR_CLIENT_ID_HERE
OAUTH_GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET_HERE
```

With your actual values from the credentials dialog.

---

## ⚠️ Important Notes

- **For development:** Use `http://localhost:8000`
- **For production:** Add `https://yourdomain.com/connect/google/check` later
- **Keep Secret Private:** Never commit `.env.local` to git
- **Wait 5 minutes:** Changes can take a few minutes to apply

---

## 🔍 Troubleshooting

**Q: Where do I find these values?**
A: You're on the right page! Fill in the URI fields as shown above.

**Q: Can I use a different port?**
A: Yes, if using port 3000: change `8000` to `3000` in both fields.

**Q: Do I need JavaScript Origins?**
A: Only if you use Google Sign-In from browser JavaScript. For server-side (recommended), it's optional but recommended to add.

---

**Next Action:** Fill in the two URI fields above, then click Create!
