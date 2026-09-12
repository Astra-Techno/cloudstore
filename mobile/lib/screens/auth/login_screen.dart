import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../app/providers/auth_provider.dart';
import '../../app/providers/bootstrap_provider.dart';
import '../../config/app_config.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _phoneController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  @override
  void dispose() {
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _requestOtp() async {
    if (!_formKey.currentState!.validate()) return;
    final success = await context
        .read<AuthProvider>()
        .requestOtp(_phoneController.text.trim());
    if (success && mounted) {
      context.push('/otp-verify', extra: _phoneController.text.trim());
    }
  }

  @override
  Widget build(BuildContext context) {
    final bootstrap = context.watch<BootstrapProvider>();
    final auth = context.watch<AuthProvider>();
    final primary = Theme.of(context).colorScheme.primary;
    final storeName = bootstrap.tenantName ?? AppConfig.appName;

    return Scaffold(
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight),
              child: IntrinsicHeight(
                child: Column(children: [
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.fromLTRB(24, 28, 24, 70),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(colors: [
                        primary,
                        Color.lerp(primary, Colors.black, .22)!
                      ], begin: Alignment.topLeft, end: Alignment.bottomRight),
                      borderRadius: const BorderRadius.vertical(
                          bottom: Radius.circular(34)),
                    ),
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('WELCOME',
                              style: TextStyle(
                                  color: Colors.white70,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 1.5)),
                          const SizedBox(height: 12),
                          Row(children: [
                            _BrandMark(logoUrl: bootstrap.logoUrl),
                            const SizedBox(width: 14),
                            Expanded(
                                child: Text(storeName,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 25,
                                        height: 1.1,
                                        fontWeight: FontWeight.w900,
                                        letterSpacing: -.7))),
                          ]),
                          const SizedBox(height: 24),
                          const Text('Your favourites, fresh from the store.',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 15,
                                  fontWeight: FontWeight.w600)),
                        ]),
                  ),
                  Transform.translate(
                    offset: const Offset(0, -34),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 18),
                      child: Card(
                        elevation: 7,
                        shadowColor: primary.withAlpha(45),
                        child: Padding(
                          padding: const EdgeInsets.all(20),
                          child: Form(
                            key: _formKey,
                            child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  const Text('Continue with your phone',
                                      style: TextStyle(
                                          fontSize: 20,
                                          fontWeight: FontWeight.w900,
                                          letterSpacing: -.4)),
                                  const SizedBox(height: 6),
                                  Text(
                                      'We’ll send a one-time verification code.',
                                      style: TextStyle(
                                          color: Colors.grey.shade600,
                                          fontSize: 13)),
                                  const SizedBox(height: 22),
                                  TextFormField(
                                    controller: _phoneController,
                                    keyboardType: TextInputType.phone,
                                    autofillHints: const [
                                      AutofillHints.telephoneNumber
                                    ],
                                    decoration: InputDecoration(
                                        labelText: 'Mobile number',
                                        hintText: 'Enter your 10-digit number',
                                        prefixIcon:
                                            const Icon(Icons.phone_rounded),
                                        prefixText: '+91  ',
                                        filled: true,
                                        fillColor: const Color(0xFFFFFBFC),
                                        border: OutlineInputBorder(
                                            borderRadius:
                                                BorderRadius.circular(14))),
                                    validator: (value) => value == null ||
                                            value.trim().length < 10
                                        ? 'Enter a valid 10-digit mobile number'
                                        : null,
                                  ),
                                  if (auth.error != null) ...[
                                    const SizedBox(height: 12),
                                    Container(
                                        padding: const EdgeInsets.all(11),
                                        decoration: BoxDecoration(
                                            color: Colors.red.shade50,
                                            borderRadius:
                                                BorderRadius.circular(12)),
                                        child: Text(auth.error!,
                                            style: TextStyle(
                                                color: Colors.red.shade800,
                                                fontSize: 13))),
                                  ],
                                  const SizedBox(height: 18),
                                  FilledButton.icon(
                                    onPressed:
                                        auth.isLoading ? null : _requestOtp,
                                    icon: auth.isLoading
                                        ? const SizedBox(
                                            width: 18,
                                            height: 18,
                                            child: CircularProgressIndicator(
                                                strokeWidth: 2,
                                                color: Colors.white))
                                        : const Icon(
                                            Icons.arrow_forward_rounded),
                                    label: Text(auth.isLoading
                                        ? 'Sending code…'
                                        : 'Get OTP'),
                                  ),
                                  const SizedBox(height: 15),
                                  Text(
                                      'By continuing, you agree to receive order updates on this number.',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(
                                          color: Colors.grey.shade600,
                                          fontSize: 11,
                                          height: 1.35)),
                                ]),
                          ),
                        ),
                      ),
                    ),
                  ),
                  const Spacer(),
                  Padding(
                      padding: const EdgeInsets.fromLTRB(24, 0, 24, 20),
                      child: Text('Powered by CloudMarket',
                          style: TextStyle(
                              color: Colors.grey.shade500,
                              fontSize: 11,
                              fontWeight: FontWeight.w600))),
                ]),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _BrandMark extends StatelessWidget {
  final String? logoUrl;
  const _BrandMark({this.logoUrl});

  @override
  Widget build(BuildContext context) {
    final fallback = Container(
        width: 58,
        height: 58,
        decoration: BoxDecoration(
            color: Colors.white, borderRadius: BorderRadius.circular(18)),
        child: Icon(Icons.storefront_rounded,
            color: Theme.of(context).colorScheme.primary, size: 30));
    if (logoUrl == null || logoUrl!.isEmpty) return fallback;
    return ClipRRect(
        borderRadius: BorderRadius.circular(18),
        child: Image.network(AppConfig.assetUrl(logoUrl!),
            width: 58,
            height: 58,
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) => fallback));
  }
}
