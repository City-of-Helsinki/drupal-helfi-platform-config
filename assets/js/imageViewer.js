import Panzoom from '@panzoom/panzoom';

((Drupal, once) => {
  // Smallest zoom level as a multiplier of the image size.
  const MIN_SCALE = 1;
  // Largest zoom level as a multiplier of the image size.
  const MAX_SCALE = 4;
  // Pixels the view moves per arrow key press.
  const PAN_STEP = 50;
  // Trackpad pinch zoom speed, higher values zoom faster.
  const PINCH_SPEED = 0.014;
  // Mouse wheel zoom speed, higher values zoom faster.
  const WHEEL_STEP = 0.05;
  // Pinch and wheel zoom can stop slightly off the 1×, 2×, 3× or 4× levels.
  // Treat values within this distance of those levels as equal to them.
  // For example, 0.01 means 3.0009× is treated as 3× when handling zoom buttons
  // and panning. The actual zoom level stays unchanged.
  const SCALE_TOLERANCE = 0.01;

  // Move the view in the arrow direction.
  const PAN_KEYS = {
    ArrowLeft: [1, 0],
    ArrowRight: [-1, 0],
    ArrowUp: [0, 1],
    ArrowDown: [0, -1],
  };

  let dialog;
  let image;
  let caption;
  let level;
  let zoomInButton;
  let zoomOutButton;
  let panzoom;
  let opener;
  let status;
  let stage;

  /**
   * Show or hide the loading spinner.
   */
  const setLoading = (loading) => {
    status.innerHTML = loading
      ? `<div class="hds-loading-spinner"><div></div><div></div><div></div></div>
        <span class="visually-hidden">${Drupal.t('Loading image', {}, { context: 'Image viewer' })}</span>`
      : '';
  };

  /**
   * Hide the spinner when the image is ready.
   */
  const onImageLoad = () => {
    if (!dialog.open) return;
    setLoading(false);
    image.style.removeProperty('visibility');
  };

  /**
   * Show an error when the image fails to load.
   */
  const onImageError = () => {
    if (!dialog.open) return;
    status.textContent = Drupal.t('The image could not be loaded.', {}, { context: 'Image viewer' });
  };

  /**
   * Check if a button is disabled.
   */
  const isDisabled = (button) => button.getAttribute('aria-disabled') === 'true';

  /**
   * Update the zoom level and button states.
   */
  const updateControls = () => {
    if (!panzoom) return;

    const scale = panzoom.getScale();
    level.textContent = `${Math.round(scale * 10) / 10}×`;
    zoomOutButton.setAttribute('aria-disabled', String(scale <= MIN_SCALE + SCALE_TOLERANCE));
    zoomInButton.setAttribute('aria-disabled', String(scale >= MAX_SCALE - SCALE_TOLERANCE));
  };

  /**
   * Keep the image centered at 1× and its edges within the stage when zoomed.
   */
  const constrainPan = (event) => {
    if (!panzoom) return;

    const scale = panzoom.getScale();
    const { x, y } = panzoom.getPan();

    if (scale <= MIN_SCALE + SCALE_TOLERANCE) {
      if (x !== 0 || y !== 0) panzoom.reset({ animate: true });
      return;
    }

    const maxX = Math.max(0, (image.offsetWidth * scale - stage.clientWidth) / (2 * scale));
    const maxY = Math.max(0, (image.offsetHeight * scale - stage.clientHeight) / (2 * scale));
    const clampedX = Math.min(maxX, Math.max(-maxX, x));
    const clampedY = Math.min(maxY, Math.max(-maxY, y));

    if (clampedX !== x || clampedY !== y) {
      // Animate only corrections that follow a button or key press.
      panzoom.pan(clampedX, clampedY, { animate: !event?.detail?.originalEvent });
    }
  };

  /**
   * Zoom with the mouse wheel or a trackpad pinch.
   */
  const onWheel = (event) => {
    if (!panzoom) return;

    // Zoom trackpad pinches in proportion to the gesture.
    if (event.ctrlKey) {
      event.preventDefault();
      const scale = panzoom.getScale() * Math.exp(-event.deltaY * PINCH_SPEED);
      panzoom.zoomToPoint(scale, event, { animate: false }, event);
      return;
    }

    panzoom.zoomWithWheel(event, { step: WHEEL_STEP });
  };

  /**
   * Zoom to a whole zoom level.
   */
  const zoomToLevel = (scale) => {
    if (scale <= MIN_SCALE) {
      panzoom.reset({ animate: true });
      return;
    }
    panzoom.zoom(Math.min(scale, MAX_SCALE), { animate: true });
  };

  /**
   * Pan the zoomed image with the arrow keys.
   */
  const onKeydown = (event) => {
    const direction = PAN_KEYS[event.key];
    if (!direction || !panzoom) return;

    const scale = panzoom.getScale();
    if (scale <= MIN_SCALE + SCALE_TOLERANCE) return;

    event.preventDefault();
    panzoom.pan((direction[0] * PAN_STEP) / scale, (direction[1] * PAN_STEP) / scale, {
      animate: true,
      relative: true,
    });
  };

  /**
   * Clean up the image viewer and return focus.
   */
  const onClose = () => {
    panzoom?.reset({ animate: false });
    panzoom?.destroy();
    panzoom = null;
    image.removeAttribute('src');
    setLoading(false);
    document.documentElement.style.removeProperty('overflow');
    opener?.focus();
  };

  /**
   * Create the image viewer dialog.
   */
  const createDialog = () => {
    dialog = document.createElement('dialog');
    dialog.className = 'image-viewer__dialog';
    dialog.setAttribute('aria-label', Drupal.t('Image viewer', {}, { context: 'Image viewer' }));
    dialog.innerHTML = `
      <div class="image-viewer__toolbar">
        <button type="button" class="image-viewer__button image-viewer__button--zoom-out">
          ${Drupal.t('Zoom out', {}, { context: 'Image viewer' })}
        </button>
        <span class="image-viewer__level"></span>
        <button type="button" class="image-viewer__button image-viewer__button--zoom-in">
          ${Drupal.t('Zoom in', {}, { context: 'Image viewer' })}
        </button>
        <button type="button" class="image-viewer__button image-viewer__button--close">
          ${Drupal.t('Close', {}, { context: 'Image viewer' })}
        </button>
      </div>
      <div class="image-viewer__stage">
        <img class="image-viewer__image" alt="">
        <div class="image-viewer__status" role="status"></div>
      </div>
      <p class="image-viewer__caption"></p>
    `;

    stage = dialog.querySelector('.image-viewer__stage');
    image = dialog.querySelector('.image-viewer__image');
    caption = dialog.querySelector('.image-viewer__caption');
    status = dialog.querySelector('.image-viewer__status');
    level = dialog.querySelector('.image-viewer__level');
    zoomInButton = dialog.querySelector('.image-viewer__button--zoom-in');
    zoomOutButton = dialog.querySelector('.image-viewer__button--zoom-out');

    zoomInButton.addEventListener('click', () => {
      if (isDisabled(zoomInButton)) return;
      zoomToLevel(Math.floor(panzoom.getScale() + SCALE_TOLERANCE) + 1);
    });

    zoomOutButton.addEventListener('click', () => {
      if (isDisabled(zoomOutButton)) return;
      zoomToLevel(Math.ceil(panzoom.getScale() - SCALE_TOLERANCE) - 1);
    });

    dialog.querySelector('.image-viewer__button--close').addEventListener('click', () => dialog.close());
    stage.addEventListener('wheel', onWheel, { passive: false });
    image.addEventListener('panzoomchange', updateControls);
    image.addEventListener('panzoomchange', constrainPan);
    window.addEventListener('resize', () => constrainPan());
    image.addEventListener('load', onImageLoad);
    image.addEventListener('error', onImageError);
    dialog.addEventListener('keydown', onKeydown);
    dialog.addEventListener('close', onClose);

    document.body.append(dialog);
  };

  /**
   * Open the image viewer for a trigger.
   */
  const open = (trigger) => {
    if (!dialog) createDialog();

    opener = trigger;
    const { width, height, alt, caption: text, credit } = trigger.dataset;

    image.width = Number(width);
    image.height = Number(height);
    image.alt = alt ?? '';
    image.style.visibility = 'hidden';
    setLoading(true);
    image.src = trigger.href;

    const parts = [text, credit && `${Drupal.t('Photo', {}, { context: 'Image photographer' })}: ${credit}`].filter(
      Boolean,
    );
    caption.textContent = parts.join(' ');
    caption.hidden = !parts.length;

    document.documentElement.style.overflow = 'hidden';
    dialog.showModal();

    panzoom = Panzoom(image, {
      canvas: true,
      maxScale: MAX_SCALE,
      minScale: MIN_SCALE,
      panOnlyWhenZoomed: true,
    });
    updateControls();
  };

  Drupal.behaviors.imageViewer = {
    attach(context) {
      once('image-viewer', '.image-viewer__trigger', context).forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
          event.preventDefault();
          open(trigger);
        });
      });
    },
  };
})(Drupal, once);
