document.addEventListener('DOMContentLoaded', function () {
    // Get localized variables for REST URL and nonce
    const aiChatbotVars = window.aiChatbotVars || { restUrl: '', nonce: '' };

    // Locate the container rendered by the shortcode
    const chatbotContainer = document.getElementById('ai-chatbot');
    if (!chatbotContainer) {
        console.error('Chatbot container not found');
        return;
    }

    // Build the chatbot UI inside the container
    chatbotContainer.innerHTML = `
        <div id="ai-chatbot-container">
            <div id="ai-chatbot-header">
                <span>Aivoma AI Chat</span>
                <button id="ai-chatbot-toggle">-</button>
            </div>
            <div id="ai-chatbot-body">
                <div id="ai-chatbot-messages"></div>
                <div id="ai-chatbot-input-container">
                    <input type="text" id="ai-chatbot-input" placeholder="Ask anything..." />
                    <button id="ai-chatbot-send">Ask</button>
                </div>
            </div>
        </div>
    `;

    // Select UI elements
    const toggleButton = chatbotContainer.querySelector('#ai-chatbot-toggle');
    const chatbotBody = chatbotContainer.querySelector('#ai-chatbot-body');
    const sendButton = chatbotContainer.querySelector('#ai-chatbot-send');
    const inputField = chatbotContainer.querySelector('#ai-chatbot-input');
    const messagesDiv = chatbotContainer.querySelector('#ai-chatbot-messages');

    // Toggle the chat body visibility
    toggleButton.addEventListener('click', function () {
        if (chatbotBody.style.display === 'none') {
            chatbotBody.style.display = 'flex';
            toggleButton.innerText = '-';
        } else {
            chatbotBody.style.display = 'none';
            toggleButton.innerText = '+';
        }
    });

    // Append a new message to the chat
    function appendMessage(sender, message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${sender}`;
        messageDiv.innerHTML = message;
        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    // Update the last message (useful for loading/error updates)
    function updateLastMessage(sender, message) {
        const lastMessage = messagesDiv.querySelector(`.chat-message.${sender}:last-child`);
        if (lastMessage) {
            lastMessage.innerHTML = message;
        }
    }

    // Send a message to the backend via the REST API
    async function sendMessage() {
        const userMessage = inputField.value.trim();
        if (!userMessage) return;

        appendMessage('user', userMessage);
        inputField.value = '';

        appendMessage('bot', 'Thinking...');

        try {
            const response = await fetch(aiChatbotVars.restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiChatbotVars.nonce
                },
                body: JSON.stringify({ message: userMessage })
            });
            const data = await response.json();
            if (data.success) {
                updateLastMessage('bot', data.reply);
            } else {
                updateLastMessage('bot', `Error: ${data.error}`);
            }
        } catch (error) {
            updateLastMessage('bot', 'Oops! Something went wrong.');
        }
    }

    sendButton.addEventListener('click', sendMessage);
    inputField.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
});

