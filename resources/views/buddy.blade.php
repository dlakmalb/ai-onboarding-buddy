<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Onboarding Buddy</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 bg-cover bg-center" style="background-image: url('{{ asset('bg.png') }}');">
  <div class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-2xl pb-10">
      <h2 class="text-3xl font-bold mb-6">🧠 Onboarding Buddy</h2>

      <form id="askForm" class="flex mb-6">
        <input
          type="text"
          id="question"
          placeholder="Ask something..."
          required
          class="flex-grow p-4 text-lg bg-gray-800 text-white placeholder-gray-400 rounded-l-lg focus:ring-2 focus:ring-blue-500"
        />
        <button
          id="askButton"
          type="submit"
          class="relative px-6 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-r-lg flex items-center justify-center"
        >
          <span id="buttonText">Ask</span>
          <svg
            id="loader"
            class="hidden animate-spin h-5 w-5 ml-2 text-white"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
              d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
            </path>
          </svg>
        </button>
      </form>

      <div
        id="responseBox"
        class="bg-gray-800 text-gray-100 p-6 rounded-lg shadow-lg min-h-[120px] whitespace-pre-wrap"
      >
        <!-- AI answer appears here -->
      </div>
    </div>
  </div>

  <script>
    const input = document.getElementById('question');
    const responseBox = document.getElementById('responseBox');
    input.addEventListener('input', () => {
        if (input.value.trim() === '') {
        responseBox.innerText = '';
        }
    });

    const form = document.getElementById('askForm');
    const button = document.getElementById('askButton');
    const buttonText = document.getElementById('buttonText');
    const loader = document.getElementById('loader');


    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const question = input.value.trim();
      if (!question) return;

      // Show loader
      button.disabled = true;
      buttonText.textContent = 'Processing';
      loader.classList.remove('hidden');

      try {
        const res = await fetch('/onboarding-buddy/ask', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
          },
          body: JSON.stringify({ question }),
        });

        if (!res.ok) {
            // parse error message if provided
            let errMsg = 'Oops! Something went wrong.';
            try {
            const errData = await res.json();
            errMsg = errData.error || errData.message || errMsg;
            } catch (_) {}
            responseBox.innerText = "Sorry something went wrong. Please try again 🙁";
        } else {
            const data = await res.json();
            responseBox.innerText = data.answer || 'No answer returned.';
        }
      } catch (err) {
        responseBox.innerText = 'Error: ' + err.message;
      } finally {
        // Hide loader
        button.disabled = false;
        buttonText.textContent = 'Ask';
        loader.classList.add('hidden');
      }
    });
  </script>
</body>
</html>
