# Flutter & Play Store Split Compat
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }
-keep class io.flutter.app.FlutterPlayStoreSplitApplication { *; }
-dontwarn io.flutter.embedding.**
-dontwarn io.flutter.app.FlutterPlayStoreSplitApplication
-dontwarn com.google.android.play.core.**
-dontwarn com.google.android.play.core.splitcompat.**
-keep class com.google.android.play.core.splitcompat.** { *; }

# Firebase
-keep class com.google.firebase.** { *; }
-dontwarn com.google.firebase.**

# Sentry
-keep class io.sentry.** { *; }
-dontwarn io.sentry.**

# Geolocator
-keep class com.baseflow.geolocator.** { *; }

# Keep annotations
-keepattributes *Annotation*
-keepattributes SourceFile,LineNumberTable
