import 'package:flutter/material.dart';

/// Subtle entrance motion for content that arrives from an API. It honours the
/// operating system's reduced-motion preference.
class StaggeredEntrance extends StatefulWidget {
  final Widget child;
  final int index;
  final Offset beginOffset;

  const StaggeredEntrance({
    super.key,
    required this.child,
    required this.index,
    this.beginOffset = const Offset(0, .06),
  });

  @override
  State<StaggeredEntrance> createState() => _StaggeredEntranceState();
}

class _StaggeredEntranceState extends State<StaggeredEntrance>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 360),
    );
    final delay = Duration(milliseconds: widget.index.clamp(0, 7).toInt() * 45);
    Future<void>.delayed(delay, () {
      if (mounted) _controller.forward();
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.disableAnimationsOf(context)) return widget.child;
    final curve =
        CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic);
    return FadeTransition(
      opacity: curve,
      child: SlideTransition(
        position: Tween<Offset>(begin: widget.beginOffset, end: Offset.zero)
            .animate(curve),
        child: widget.child,
      ),
    );
  }
}

/// Material tap feedback with a short scale-down that feels responsive without
/// making navigation or checkout actions visually noisy.
class PressableScale extends StatefulWidget {
  final Widget child;
  final VoidCallback? onTap;
  final BorderRadius borderRadius;
  final double pressedScale;

  const PressableScale({
    super.key,
    required this.child,
    required this.onTap,
    this.borderRadius = const BorderRadius.all(Radius.circular(16)),
    this.pressedScale = .975,
  });

  @override
  State<PressableScale> createState() => _PressableScaleState();
}

class _PressableScaleState extends State<PressableScale> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final canAnimate = !MediaQuery.disableAnimationsOf(context);
    return AnimatedScale(
      scale: _pressed && canAnimate ? widget.pressedScale : 1,
      duration: const Duration(milliseconds: 110),
      curve: Curves.easeOut,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: widget.borderRadius,
          onHighlightChanged: (value) => setState(() => _pressed = value),
          onTap: widget.onTap,
          child: widget.child,
        ),
      ),
    );
  }
}
