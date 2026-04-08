## Google OAuth2 Setup Guide for Credix Insurance App

### Current Status
✅ Code implementation complete
✅ Environment variables configured in `.env.local`
⏳ Waiting for Google OAuth credentials

---

## Step 1: Get Your Google OAuth Credentials

### 1.1 Go to Google Cloud Console
- Visit: https://console.cloud.google.com/
- Sign in with your Google account

### 1.2 Create/Select a Project
- Click the project selector at the top
- Click "NEW PROJECT"
- Enter project name: `Credix Insurance`
- Click "CREATE"

### 1.3 Enable Google+ API
- In the search bar, search for "Google+ API"
- Click on "Google+ API" in results
- Click the "ENABLE" button
- Wait for enabling to complete

### 1.4 Create OAuth 2.0 Credentials
- In the left sidebar, click "Credentials"
- Click "+ CREATE CREDENTIALS" button
- Select "OAuth 2.0 Client ID"
- If prompted, configure the OAuth consent screen:
  - Select "External" user type
  - Fill in required fields (app name, your email, etc.)
  - Add scopes: `email`, `profile`, `openid`
  - Save and continue

### 1.5 Configure Web Application
- For Application type, select "Web application"
- Name: `Credix Insurance App`
- Under "Authorized redirect URIs", add:
  ```
  http://localhost:8000/connect/google/check
  ```
- Click "CREATE"

### 1.6 Copy Your Credentials
After creation, a dialog appears with:
- **Client ID** (looks like: `123456789-xxxxx.apps.googleusercontent.com`)
- **Client Secret** (looks like: `GOCSPX-xxxxxx`)

**Copy both values!**

---

## Step 2: Add Credentials to Your App

### 2.1 Update .env.local
Open `.env.local` and update these lines:

```env
OAUTH_GOOGLE_CLIENT_ID=YOUR_CLIENT_ID_HERE
OAUTH_GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET_HERE
```

Replace:
- `YOUR_CLIENT_ID_HERE` with your actual Client ID from Step 1.6
- `YOUR_CLIENT_SECRET_HERE` with your actual Client Secret from Step 1.6

Example (DO NOT USE - JUST AN EXAMPLE):
```env
OAUTH_GOOGLE_CLIENT_ID=123456789-abcdefghijklmno.apps.googleusercontent.com
OAUTH_GOOGLE_CLIENT_SECRET=GOCSPX-abcdefghijk1234567890
```

### 2.2 Save the File
Save `.env.local` - no application restart needed, Symfony will read the updated values

---

## Step 3: Test Google Login

### 3.1 Start Your App
```bash
symfony server:start
# or
php -S localhost:8000 -t public
```

### 3.2 Try Google Sign-Up
1. Go to: http://localhost:8000/signup
2. Click the "Sign up with Google" button
3. You'll be redirected to Google login

### 3.3 Login with Your Account
- Sign in with your Google account
- Authorize the app to access your profile and email
- You'll be automatically logged in to Credix

---

## Step 4: Production Setup (Later)

When you deploy to production:

### 4.1 Add Production Redirect URI
1. Return to Google Cloud Console
2. Go to Credentials → Your OAuth 2.0 Client ID
3. Click "Edit" (pencil icon)
4. Add this to "Authorized redirect URIs":
   ```
   https://yourdomain.com/connect/google/check
   ```
5. Click "SAVE"

### 4.2 Update Production .env
Add to your production environment:
```env
OAUTH_GOOGLE_CLIENT_ID=your_client_id
OAUTH_GOOGLE_CLIENT_SECRET=your_client_secret
```

---

## Troubleshooting

### Error: "OAuth client was not found" / Error 401: invalid_client
**Cause**: Client ID/Secret not set in `.env.local`
**Fix**: 
1. Verify credentials in `.env.local` are correct
2. Check no extra spaces around the values
3. Restart PHP server if running

### Error: "Authorization Error: redirect_uri mismatch"
**Cause**: Redirect URI in Google Cloud doesn't match app URL
**Fix**:
1. Go to Google Cloud Credentials
2. Edit the OAuth 2.0 Client ID
3. Check "Authorized redirect URIs" includes:
   - http://localhost:8000/connect/google/check (for local)
   - https://yourdomain.com/connect/google/check (for production)

### Error: "Access Blocked" / "This app is not verified"
**Cause**: Normal for development apps
**Fix**:
1. Click "Advanced"
2. Click "Go to [app name] (unsafe)"
3. This is safe during development

---

## Features Implemented

✅ **Sign Up with Google**
- Users click "Sign up with Google"
- Auto-created account with Google email
- Phone automatically verified
- Account status set to ACTIF

✅ **Login with Google**
- Users click "Continue with Google"
- Auto-login if account exists
- No password needed

✅ **Account Auto-Setup**
- Email: From Google profile
- Name: From Google profile
- Phone verification: ✅ Pre-verified
- Account status: ACTIF (active)
- Password: Securely generated (not used)

---

## File Structure

```
src/
├── Security/
│   └── GoogleAuthenticator.php         ← OAuth logic
├── Controller/
│   └── GoogleOAuthController.php       ← Routes & redirects
templates/
├── auth/
│   ├── signup.html.twig               ← "Sign up with Google" button
│   └── login.html.twig                ← "Continue with Google" button
config/
└── packages/
    └── security.yaml                  ← Security configuration
.env.local                             ← Credentials storage
```

---

## Support

If you encounter issues:
1. Check `.env.local` has correct credentials
2. Verify redirect URI matches in Google Cloud Console
3. Check logs: `var/log/dev.log`
4. Ensure PHP server is running: `localhost:8000`

---

Generated: April 3, 2026
