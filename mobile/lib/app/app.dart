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
        colorSchemeSeed: bootstrap.primaryColor ?? Colors.blue,
        useMaterial3: true,
      ),
      routerConfig: appRouter,
    );
  }
}
