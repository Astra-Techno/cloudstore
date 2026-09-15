import 'package:flutter/material.dart';

import 'app_config.dart';

/// The single source of truth for customer-app colours.
///
/// `PRIMARY_COLOR` sets the first-frame APK colour and the tenant branding
/// returned by bootstrap replaces it as soon as it is available. Keep screen
/// code on `Theme.of(context).colorScheme.primary` for tenant-safe actions.
final class AppTheme {
  static const Color cloudMarketRed = Color(0xFFE23744);
  static const Color ink = Color(0xFF1C1C1C);
  static const Color canvas = Colors.white;

  static Color get buildPrimary => AppConfig.fallbackPrimaryColor;

  static Color resolvePrimary(Color? tenantPrimary) =>
      tenantPrimary ?? buildPrimary;

  static Color primaryDark(Color primary) =>
      Color.lerp(primary, Colors.black, .20)!;

  static Color primaryLight(Color primary) =>
      Color.lerp(primary, Colors.white, .91)!;

  static LinearGradient primaryGradient(Color primary) => LinearGradient(
        colors: [primary, primaryDark(primary)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      );

  static ThemeData light(Color primary) {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: primary,
      brightness: Brightness.light,
      surface: canvas,
    ).copyWith(
      primary: primary,
      onPrimary: Colors.white,
      secondary: primary,
      surface: canvas,
    );

    return ThemeData(
      colorScheme: colorScheme,
      useMaterial3: true,
      scaffoldBackgroundColor: canvas,
      splashFactory: InkSparkle.splashFactory,
      dividerTheme: const DividerThemeData(
        color: Color(0xFFF0F0F0),
        space: 1,
        thickness: 1,
      ),
      textTheme: const TextTheme(
        headlineSmall: TextStyle(
          color: ink,
          fontWeight: FontWeight.w900,
          letterSpacing: -.65,
        ),
        titleLarge: TextStyle(
          color: ink,
          fontWeight: FontWeight.w800,
          letterSpacing: -.4,
        ),
        titleMedium: TextStyle(color: ink, fontWeight: FontWeight.w800),
        bodyLarge: TextStyle(color: ink),
        bodyMedium: TextStyle(color: ink),
      ),
      appBarTheme: const AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: canvas,
        foregroundColor: ink,
        titleTextStyle: TextStyle(
          fontSize: 19,
          fontWeight: FontWeight.w800,
          letterSpacing: -.3,
        ),
      ),
      cardTheme: const CardThemeData(
        elevation: 0,
        margin: EdgeInsets.zero,
        color: canvas,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(20)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: canvas,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: Color(0xFFF1F1F1)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: primary, width: 1.5),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 72,
        backgroundColor: canvas,
        indicatorColor: primary.withAlpha(25),
        labelTextStyle: WidgetStateProperty.all(
          const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
        ),
      ),
      chipTheme: ChipThemeData(
        side: BorderSide(color: primary.withAlpha(45)),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        labelStyle: const TextStyle(fontWeight: FontWeight.w700),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(52),
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: primary,
          minimumSize: const Size(0, 48),
          side: BorderSide(color: primary.withAlpha(85)),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          textStyle: const TextStyle(fontWeight: FontWeight.w800),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: ink,
        contentTextStyle: const TextStyle(color: Colors.white),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: canvas,
        modalBackgroundColor: canvas,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
      ),
      dialogTheme: const DialogThemeData(
        backgroundColor: canvas,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(24)),
        ),
      ),
    );
  }
}
