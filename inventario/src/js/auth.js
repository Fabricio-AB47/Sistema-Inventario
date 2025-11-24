const authForm = document.getElementById('auth-form');
const authMessage = document.getElementById('auth-message');

if (authForm) {
  authForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('email').value.trim();
    // Password fijo solicitado: "psw"
    const password = document.getElementById('password').value || 'psw';

    authMessage.textContent = 'Procesando...';

    try {
      const res = await fetch('../api/auth.php?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Error');
      authMessage.textContent = 'Listo, redirigiendo...';
      window.location.href = 'dashboard.php';
    } catch (err) {
      authMessage.textContent = err.message;
    }
  });
}
