import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/bootstrap_provider.dart';
import 'router.dart';
import '../config/app_config.dart';

class CloudStoreApp extends StatelessWidget {
  const CloudStoreApp({super.key});

  @override
  Widget build(BuildContext context) {
    final bootstrap = context.watch<BootstrapProvider>();

    return MaterialApp.router(
      title: bootstrap.tenantName ?? AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: bootstrap.primaryColor ?? AppConfig.fallbackPrimaryColor,
          brightness: Brightness.light,
          surface: Colors.white,
        ),
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xFFFFFBFC),
        appBarTheme: const AppBarTheme(
          centerTitle: false,
          elevation: 0,
          scrolledUnderElevation: 0,
          backgroundColor: Colors.white,
          foregroundColor: Color(0xFF1C1C1C),
          titleTextStyle: TextStyle(
              fontSize: 19, fontWeight: FontWeight.w800, letterSpacing: -.3),
        ),
        cardTheme: const CardThemeData(
          elevation: 0,
          margin: EdgeInsets.zero,
          color: Colors.white,
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.all(Radius.circular(20))),
        ),
        navigationBarTheme: NavigationBarThemeData(
          height: 72,
          backgroundColor: Colors.white,
          indicatorColor:
              (bootstrap.primaryColor ?? AppConfig.fallbackPrimaryColor)
                  .withAlpha(25),
          labelTextStyle: WidgetStateProperty.all(
              const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
        ),
        filledButtonTheme: FilledButtonThemeData(
            style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
        )),
      ),
      routerConfig: appRouter,
    );
  }
}
