// assets/password_validation.js
// - Valide les règles : 12 caractères min, 1 spécial min, 1 chiffre min
// - Met à jour les classes DSFR-like (fr-message--valid / fr-message--error)
// - Ajoute un indicateur "Force : Faible/Moyen/Fort"
// - Ajoute un toggle "œil" dans le champ (bouton #password-toggle-btn)

(function () {
  function byId(id) {
    return document.getElementById(id);
  }

  const input = byId("password-validation-input");
  const messages = byId("password-validation-input-messages");
  const toggleBtn = byId("password-toggle-btn");

  // Si la page n'a pas le composant password, on ne fait rien.
  if (!input || !messages) return;

  function validate(pw) {
    const len12 = pw.length >= 12;
    const special = /[^A-Za-z0-9]/.test(pw);
    const digit = /[0-9]/.test(pw);

    // Score simple pour "Force"
    const upper = /[A-Z]/.test(pw);
    const lower = /[a-z]/.test(pw);

    let score = 0;
    if (pw.length >= 8) score++;
    if (pw.length >= 12) score++;
    if (pw.length >= 16) score++;
    if (digit) score++;
    if (special) score++;
    if (upper) score++;
    if (lower) score++;

    let label = "Faible";
    if (score >= 5) label = "Moyen";
    if (score >= 7) label = "Fort";

    const rulesOk = len12 && special && digit;

    return { len12, special, digit, rulesOk, score, label };
  }

  function setRuleClass(el, ok) {
    el.classList.toggle("fr-message--valid", ok);
    el.classList.toggle("fr-message--error", !ok);
  }

  function ensureStrengthLine() {
    let el = byId("password-strength-line");
    if (!el) {
      el = document.createElement("p");
      el.id = "password-strength-line";
      el.className = "fr-message";
      messages.appendChild(el);
    }
    return el;
  }

  function updateUI() {
    const pw = input.value || "";
    const v = validate(pw);

    // Les lignes de règles : ce sont celles qui ont data-fr-valid / data-fr-error
    // On suppose l'ordre : 12 chars, 1 spécial, 1 chiffre (comme ton HTML)
    const ruleEls = messages.querySelectorAll(
      'p.fr-message[data-fr-valid][data-fr-error]'
    );

    if (ruleEls.length >= 3) {
      setRuleClass(ruleEls[0], v.len12);
      setRuleClass(ruleEls[1], v.special);
      setRuleClass(ruleEls[2], v.digit);
    }

    // Force
    const strengthEl = ensureStrengthLine();
    strengthEl.textContent = `Force : ${v.label}`;

    // Accessibilité : aria-invalid reflète le respect des règles minimales
    input.setAttribute("aria-invalid", String(!v.rulesOk));
  }

  // Toggle "œil" dans le champ (bouton)
  if (toggleBtn) {
    toggleBtn.addEventListener("click", function () {
      const currentlyText = input.type === "text";
      input.type = currentlyText ? "password" : "text";

      const nowShown = !currentlyText;
      toggleBtn.classList.toggle("is-on", nowShown);
      toggleBtn.setAttribute("aria-pressed", String(nowShown));
      toggleBtn.setAttribute(
        "aria-label",
        nowShown ? "Masquer le mot de passe" : "Afficher le mot de passe"
      );
    });
  }

  // Mise à jour au fil de la saisie
  input.addEventListener("input", updateUI);

  // Initialisation
  updateUI();
})();
