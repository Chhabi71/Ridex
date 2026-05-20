<?php
/**
 * Purpose: Floating Ridex chatbot widget.
*/
?>
<div class="ridex-chatbot" data-ridex-chatbot>

	<!-- Hidden trigger used by chatbot.js to open the existing booking confirmation modal. -->
	<button
		class="ridex-chatbot__modal-trigger"
		type="button"
		hidden
		data-ridex-chatbot-booking-modal-trigger
		data-modal-target="user-booking-confirm-modal"
	>Open booking confirmation</button>
	<button class="ridex-chatbot__toggle" type="button" data-chatbot-toggle aria-label="Open Ridex chatbot">
		<span class="material-symbols-rounded" aria-hidden="true">smart_toy</span>
	</button>

	<section class="ridex-chatbot__panel" data-chatbot-panel aria-label="Ridex chatbot" aria-hidden="true">
		<header class="ridex-chatbot__header">
			<div>
				<p class="ridex-chatbot__eyebrow">Ridex Assistant</p>
				<h2 class="ridex-chatbot__title">Ask for a ride</h2>
			</div>
			<button class="ridex-chatbot__close" type="button" data-chatbot-close aria-label="Close chatbot">
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="ridex-chatbot__messages" data-chatbot-messages aria-live="polite">
			<div class="ridex-chatbot__message ridex-chatbot__message--bot">
				Hi! Ask me things like “I want a cheap car”, “show me electric cars”, or “best car for family”.
			</div>
		</div>

		<form class="ridex-chatbot__form" data-chatbot-form>
			<input class="ridex-chatbot__input" data-chatbot-input type="text" maxlength="500" placeholder="Type your message..." autocomplete="off" />
			<button class="ridex-chatbot__send" type="submit" aria-label="Send message">
				<span class="material-symbols-rounded" aria-hidden="true">send</span>
			</button>
		</form>
	</section>
</div>
