// Tema terang/gelap halaman beranda. Dimuat di <head> (tanpa defer) agar tema
// tersimpan terpasang sebelum render. Kunci 'theme' dibagi dengan Filament.
(function () {
    var tema = null;
    try { tema = localStorage.getItem('theme'); } catch (e) {}
    var gelap = tema === 'dark' || ((tema === null || tema === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', gelap);

    document.addEventListener('click', function (e) {
        if (! e.target.closest('[data-sakelar-tema]')) return;
        var g = ! document.documentElement.classList.contains('dark');
        try { localStorage.setItem('theme', g ? 'dark' : 'light'); } catch (err) {}
        document.documentElement.classList.toggle('dark', g);
    });
})();
