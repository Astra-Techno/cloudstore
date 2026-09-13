import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/bootstrap_provider.dart';
import 'router.dart';
import '../config/app_config.dart';
import '../config/app_theme.dart';

class CloudStoreApp extends StatelessWidget {
  const CloudStoreApp({super.key});

  @override
  Widget build(BuildContext context) {
    final bootstrap = context.watch<BootstrapProvider>();
    final primary = AppTheme.resolvePrimary(bootstrap.primaryColor);

    return MaterialApp.router(
      title: bootstrap.tenantName ?? AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(primary),
      routerConfig: appRouter,
    );
  }
}
