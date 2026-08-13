(function(){
  var ridotto = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var cassa = document.getElementById('cassa');
  if (ridotto || !cassa || !window.matchMedia('(hover: hover)').matches) return;

  // lieve basculamento della cassa al passaggio del mouse
  var raf = null;
  document.addEventListener('mousemove', function(e){
    if (raf) return;
    raf = requestAnimationFrame(function(){
      var cx = window.innerWidth / 2, cy = window.innerHeight / 2;
      var rx = ((e.clientY - cy) / cy) * -4;
      var ry = ((e.clientX - cx) / cx) *  4;
      cassa.style.transform = 'rotateX(' + rx.toFixed(2) + 'deg) rotateY(' + ry.toFixed(2) + 'deg)';
      raf = null;
    });
  });
  document.addEventListener('mouseleave', function(){
    cassa.style.transform = 'rotateX(0deg) rotateY(0deg)';
  });
})();
