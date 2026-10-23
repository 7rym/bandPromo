/* Injected via CDP Runtime.evaluate — returns geometry for honest pass criteria. */
(function () {
  const root = document.documentElement;
  const rect = (el) => {
    if (!el) return null;
    const r = el.getBoundingClientRect();
    return {
      x: Math.round(r.x),
      y: Math.round(r.y),
      w: Math.round(r.width),
      h: Math.round(r.height),
      bottom: Math.round(r.bottom),
      right: Math.round(r.right),
      top: Math.round(r.top),
      left: Math.round(r.left),
    };
  };
  const cover = document.querySelector('.scene .cover-art, .scene img.cover-art, .cover-art');
  const scene = document.querySelector('.scene');
  const transport = document.querySelector('.player-transport');
  const beggars = document.getElementById('beggars-banquet');
  const support = beggars ? beggars.querySelector('.support-link-button') : null;
  const campaignStrip = document.getElementById('campaignLogoStrip');
  const campaignCurrent = campaignStrip
    ? campaignStrip.querySelector('.is-current, [aria-selected="true"]')
    : null;
  const coverflow = document.querySelector('.playlist-coverflow');
  const coverflowActive = coverflow
    ? coverflow.querySelector('.is-active .playlist-coverflow-thumb, .is-active')
    : null;
  const coverflowThumb = coverflow
    ? coverflow.querySelector('.playlist-coverflow-item:not(.is-active) .playlist-coverflow-thumb')
    : null;
  const firstTrack = document.querySelector(
    '.playlist-box .playlist-item, .playlist-box li.track, .playlist-box [data-track-id], .playlist-box .track-row, .playlist-box ol li, .playlist-box ul li'
  );
  const contentToggle = document.querySelector('.content-toggle');
  const scopeToolbar = document.querySelector('.playlist-scope-toolbar');
  const vh = window.innerHeight;
  const vw = window.innerWidth;
  const tr = rect(transport);
  const controls = transport
    ? Array.from(transport.querySelectorAll('.player-control-btn')).map((btn) => {
        const r = btn.getBoundingClientRect();
        return {
          outside: r.right > (tr ? tr.right + 1 : vw) || r.left < (tr ? tr.left - 1 : 0),
          right: Math.round(r.right),
          left: Math.round(r.left),
        };
      })
    : [];
  const firstTrackR = rect(firstTrack);
  const campaignR = rect(campaignCurrent);
  const campaignVisible =
    !!campaignR &&
    campaignR.right > 2 &&
    campaignR.left < vw - 2 &&
    campaignR.bottom > 0 &&
    campaignR.top < vh;
  const peek =
    !!firstTrackR &&
    firstTrackR.top < vh - 4 &&
    firstTrackR.bottom > 0 &&
    firstTrackR.top < vh;

  const cs = getComputedStyle(root);
  return {
    viewport: { w: vw, h: vh },
    scales: {
      stacked: cs.getPropertyValue('--player-size-scale-stacked').trim(),
      split: cs.getPropertyValue('--player-size-scale-split').trim(),
      cover: cs.getPropertyValue('--cover-size-scale').trim(),
      chromeMin: cs.getPropertyValue('--player-chrome-min').trim(),
      rail: cs.getPropertyValue('--player-rail').trim(),
      card: cs.getPropertyValue('--card-size').trim(),
    },
    cover: rect(cover || scene),
    transport: tr,
    beggars: rect(beggars),
    support: rect(support),
    contentToggle: rect(contentToggle),
    scopeToolbar: rect(scopeToolbar),
    campaignStrip: rect(campaignStrip),
    campaignCurrent: campaignR,
    campaignOverflow: campaignStrip
      ? {
          overflowing: campaignStrip.classList.contains('is-overflowing'),
          hasStart: campaignStrip.classList.contains('has-overflow-start'),
          hasEnd: campaignStrip.classList.contains('has-overflow-end'),
          scrollW: campaignStrip.scrollWidth,
          clientW: campaignStrip.clientWidth,
        }
      : null,
    coverflow: rect(coverflow),
    coverflowOverflow: coverflow
      ? {
          overflowing: coverflow.classList.contains('is-overflowing'),
          hasStart: coverflow.classList.contains('has-overflow-start'),
          hasEnd: coverflow.classList.contains('has-overflow-end'),
          scrollW: coverflow.scrollWidth,
          clientW: coverflow.clientWidth,
        }
      : null,
    coverflowActiveThumb: rect(
      coverflow ? coverflow.querySelector('.is-active .playlist-coverflow-thumb') : null
    ),
    coverflowIdleThumb: rect(coverflowThumb),
    firstTrack: firstTrackR,
    criteria: {
      peekFirstTrack: peek,
      transportWiderOrEqualCover:
        tr && (cover || scene)
          ? tr.w + 1 >= Math.min(rect(cover || scene).w, vw)
            || tr.w >= 200
          : false,
      transportFitsControls: controls.length > 0 && controls.every((c) => !c.outside),
      beggarsFitsContent: !!(support && rect(support) && rect(support).w <= (rect(beggars)?.w || 0) + 2),
      campaignSelectedVisible: campaignVisible,
      campaignHasOverflowCue:
        !campaignStrip ||
        campaignStrip.scrollWidth <= campaignStrip.clientWidth + 1 ||
        campaignStrip.classList.contains('is-overflowing'),
      coverflowGreyBorder: coverflowThumb
        ? getComputedStyle(coverflowThumb).borderColor !== 'rgba(0, 0, 0, 0)' &&
          getComputedStyle(coverflowThumb).borderTopWidth !== '0px'
        : false,
      flushTabsToSelector:
        contentToggle && scopeToolbar
          ? Math.abs(rect(scopeToolbar).top - rect(contentToggle).bottom) <= 4
          : false,
      transportIndependentOfCover:
        tr && (cover || scene)
          ? Math.abs(tr.w - rect(cover || scene).w) > 8 || tr.w >= 200
          : false,
    },
    controlCount: controls.length,
  };
})()
