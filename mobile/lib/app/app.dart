import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/bootstrap_provider.dart';
import 'router.dart';

class CloudStoreApp extends StatelessWidget {
  const CloudStoreApp({super.key});

  @override
  Widget build(BuildContext context) {
    final bootstrap = context.watch<BootstrapProvider>();

    return MaterialApp.router(
      title: bootstrap.tenantName ?? 'CloudStore',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: bootstrap.primaryColor ?? const Color(0xFFE23744),
          brightness: Brightness.light,
          surface: Colors.white,
        ),
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xFFF8F8F8),
        appBarTheme: AppBarTheme(
          centerTitle: false,
          elevation: 0,
          scrolledUnderElevation: 1,
          backgroundColor: Colors.white,
          foregroundColor: const Color(0xFF1C1C1C),
          titleTextStyle: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
        ),
        cardTheme: const CardThemeData(
          elevation: 0,
          margin: EdgeInsets.zero,
          color: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(18))),
        ),
        navigationBarTheme: NavigationBarThemeData(
          height: 68,
          backgroundColor: Colors.white,
          indicatorColor: (bootstrap.primaryColor ?? const Color(0xFFE23744)).withAlpha(25),
          labelTextStyle: WidgetStateProperty.all(const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
        ),
      ),
      routerConfig: appRouter,
    );
  }
}
