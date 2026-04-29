/* Default Mock Data Global */

const APP_DATA = {
  isLogged: false,
  userType: null, // 'student', 'academic', 'admin'
};

document.addEventListener('DOMContentLoaded', () => {
  // Theme toggle initialization if requested
  const darkToggles = document.querySelectorAll('.dark-toggle');
  if(darkToggles.length) {
    const isDark = localStorage.getItem('theme') === 'dark';
    if(isDark) document.documentElement.classList.add('dark');
    
    darkToggles.forEach(btn => {
      btn.innerHTML = isDark ? '☀️' : '🌙';
      btn.addEventListener('click', () => {
        document.documentElement.classList.toggle('dark');
        const dark = document.documentElement.classList.contains('dark');
        localStorage.setItem('theme', dark ? 'dark' : 'light');
        darkToggles.forEach(b => b.innerHTML = dark ? '☀️' : '🌙');
      });
    });
  }
});
