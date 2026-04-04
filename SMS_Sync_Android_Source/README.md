# Quick SMS Sync - Android Studio Setup

This folder contains the source code for the "Quick SMS Sync" Android application. Follow these steps to build your APK:

## 🛠️ Build Instructions

1. **Open Android Studio**.
2. Select **File > New > New Project**.
3. Choose **Empty Compose Activity** and click **Next**.
4. Set the following:
   - **Name**: Quick SMS Sync
   - **Package name**: `com.example.smssync`
   - **Minimum SDK**: API 26 (Android 8.0)
5. Click **Finish**.
6. **Overwrite the files**: Copy the contents of this folder into your new project directory.
   - Specifically, replace `app/src/main/java/com/example/smssync/MainActivity.kt` and `app/src/main/AndroidManifest.xml`.
7. **Sync Gradle**: Click the "Sync Now" button in the top bar.
8. **Build APK**: Go to **Build > Build Bundle(s) / APK(s) > Build APK(s)**.

## 🚀 How to Use

1. **Install the APK** on your Android phone.
2. Open the app and **Grant SMS Permissions**.
3. Enter your **Server URL** in the settings field.
   - Example: `http://192.168.1.5:8080/api/ingest_messages.php`
4. Click **Push Last 50 Messages** to test the sync.
5. New messages will now automatically sync to your **Analysis** tab in real-time!

> [!TIP]
> Make sure your phone is on the same Wi-Fi network as your Docker server, or use a public URL if hosted.
