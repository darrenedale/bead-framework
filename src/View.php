<?php

namespace Equit;

use Equit\Contracts\Response;
use Equit\Exceptions\ViewNotFoundException;
use Equit\Exceptions\ViewRenderingException;
use Equit\Responses\DoesntHaveHeaders;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use TypeError;
use function Equit\Traversable\some;

/**
 * Encapsulates a Bead view in your app.
 *
 * Views are PHP files that render page content for your application. Views are provided with data to make them reusable
 * by passing the data through the constructor or using the `with()` method. You can inject data into all views that are
 * rendered using the `inject()` method. Do this in your application constructor or `exec()` method to ensure that any
 * view rendered receives the data. Data is available using the $data array in the view file, or by using the data key
 * name as a variable (e.g. if you provide ["foo" => "bar"] as the data for a view, both `$foo` and `$data["foo"]`
 * contain the value `"bar"`). Data that is not keyed with a valid PHP variable name is not available as a separate
 * variable in the view, and can only be accessed using the `$data` array.
 *
 * All views have access to the current `WebApplication` instance in the `$app` variable, unless it's overwritten by the
 * view's data. It is recommended that you don't overwrite this variable in your views with data, but if you do you can
 * still access the `WebApplication` instance using `WebApplication::instance()`.
 *
 * ## Layouts and Sections
 *
 * ## Includes
 * Views can be included in other views by using the `include()` method in your view file. Views included in other views
 * have access to all the data from the view that includes them, plus their own data. The view's own data takes
 * precedence over data inherited from the parent view that includes it. The data in the parent is unaffected if the
 * included view has its own data that overrides it (i.e. the parent view always retains its own array of data).
 *
 * ## Components
 * You can also use views as components in other views. This is like including views except that components don't
 * inherit the data of the parent view. Components can also receive content in slots to render. Any content between
 * the `startComponent()` and `endComponent()` is the default slot, and is available as the `$slot` variable in the
 * component. You can provide more than one slot by surrounding the content with `startSlot()` and `endSlot()`. Slots
 * created like this are named, and the content is available to the component as a variable with the name of the slot.
 * Components are useful for making re-usable HTML components based on fragments of content. For example, if your app
 * has a popup that has an anchor and some content that popup up when the anchor is clicked you might have:
 *
 * popup.php:
 * ```php
 * <div class="popup">
 * <div class="popup-anchor"><?= $anchor ?></div>
 * <div class="popup-content">
 * <?= $slot ?>
 * </div>
 * ```
 *
 * view.php:
 * ```php
 * <section>
 * ...
 * <?php View::component("popup"); ?>
 * <?php View::slot("anchor"); ?>
 * <p>Click here to show the popup</p>
 * <?php View::endSlot(); ?>
 * <p>
 * This is the content of the popup. It's all the content between component() and endComponent() that isn't a named
 * slot. You will only see this when you click the anchor.
 * </p>
 * <?php View::endComponent(); ?>
 * ```
 *
 * ## Stacks
 * Views have a concept of stacks. Stacks are named collections of content that you can add to in your views and output
 * at the appropriate point in your views/layouts. This enables views that are included in other views, used as
 * components in other views, or use other views as layouts to inject content back up the tree. The most common use case
 * is to have a stack of scripts and a stack of stylesheets which views can add to and have the top-level layout then
 * render into the &lt;head&gt; section. For example
 *
 * layout.php:
 * ```php
 * <html>
 * <head>
 * <?php View::stack("scripts"; ?>
 * </head>
 * <body>
 * <?php View::include("foo"); ?>
 * </body>
 * </html>
 * ```
 *
 * foo.php:
 * ```php
 * <p>Some content...</p>
 * <?php View::push("scripts"); ?>
 * <script type="module" src="/js/foo-module.js"></script>
 * <?php View::endPush(); ?>
 * ```
 */
class View implements Response
{
	use DoesntHaveHeaders;

	/**
	 * @var array Stack of the views that are rendering. The top view on the stack is the one currently being rendered,
	 * and this is where static calls to view-focused static methods (e.g. layout()) are directed.
	 */
	private static array $m_renderStack = [];

	/**
	 * @var array Stack of layouts. Layouts are pushed to the top of the stack when they are created, and removed when
	 * they finish rendering. This is where calls to layout-focused static methods (e.g. section(), push(),
	 * hasSection()) are directed.
	 */
	private static array $m_layoutStack = [];

	/** @var array The data injected into all views. */
	private static array $m_injectedData = [];

	/** @var View|null The layout for the view. */
	private ?View $m_layout = null;

	/** @var string|null The name of the section currently being produced, if any. */
	private ?string $m_currentSection = null;

	/** @var array<string, string> The section content, keyed by name. */
	private array $m_sections = [];

	/** @var string|null The name of the component currently being produced, if any. */
	private ?string $m_currentComponent = null;

	/** @var array The name data for the component currently being produced, if any. */
	private array $m_currentComponentData = [];

	private ?string $m_currentComponentSlotName = null;
	private array $m_currentComponentSlots = [];

	/** @var string|null The name of the stack currently being pushed to, if any. */
	private ?string $m_currentStack = null;

	/** @var bool Whether we're pushing to the current stack only if the content isn't already there. */
	private bool $m_currentStackOnce = false;

	/** @var array<string, string> The stack content, keyed by name. */
	private array $m_stacks = [];

	/** @var string The view's name. */
	private string $m_name;

	/** @var array The view's data. */
	private array $m_data = [];

	/** @var string The full path to the view file. */
	private string $m_path;

	/**
	 * Initialise a new view, providing it with some data.
	 *
	 * @param string $name
	 * @param array $data
	 *
	 * @throws ViewNotFoundException
	 */
	public function __construct(string $name, array $data = [])
	{
		$this->m_path = static::viewDirectory() . "/" . str_replace(".", DIRECTORY_SEPARATOR, $name) . ".php";

		if (!file_exists($this->m_path)) {
			throw new ViewNotFoundException($name, "View {$name} not found.");
		}

		$this->m_name = $name;
		$this->m_data = $data;
	}

	/**
	 * The full path to the directory where view files are stored.
	 *
	 * @return string The directory.
	 */
	public static function viewDirectory(): string
	{
		return Application::instance()->rootDir() . "/" . Application::instance()->config("view.directory", "views");
	}

	/**
	 * Helper to ensure valid names are used for stacks, sections, etc.
	 *
	 * @param string $name The name to check.
	 *
	 * @return bool `true` if it's valid, `false` if not.
	 */
	private static function isValidName(string $name): bool
	{
		return !empty($name) && strlen($name) === strspn($name, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_");
	}

	/**
	 * Check whether a name is valid for a section.
	 *
	 * @param string $name The name to check.
	 *
	 * @return bool `true` if it's valid, `false` if not.
	 */
	public static function isValidSectionName(string $name): bool
	{
		return self::isValidName($name);
	}

	/**
	 * Check whether a name is valid for a stack.
	 *
	 * @param string $name The name to check.
	 *
	 * @return bool `true` if it's valid, `false` if not.
	 */
	public static function isValidStackName(string $name): bool
	{
		return self::isValidName($name);
	}

	/**
	 * Fetch the view that is on top of the rendering stack.
	 *
	 * @return View|null
	 */
	protected static function currentView(): ?View
	{
		return (empty(self::$m_renderStack) ? null : self::$m_renderStack[count(self::$m_renderStack) - 1]);
	}

	/**
	 * Fetch the layout on top of the layout stack.
	 *
	 * @return Layout|null The layout view, or null if there is no view being rendered or it doesn't have a layout.
	 */
	protected static function currentLayout(): ?Layout
	{
		return (empty(self::$m_layoutStack) ? null : self::$m_layoutStack[count(self::$m_layoutStack) - 1]);
	}

	/**
	 * Inject some data into all views.
	 *
	 * Use this somewhere in your app's initialisation code (e.g. App::exec(), a plugin's constructor, your bootstrap
	 * script) to ensure all views rendered are given a piece of data. Where injected data and the data for the specific
	 * view have the same key, the data for the specific view takes precedence.
	 *
	 * @param array|string $keyOrData The array of data to add, or the key if a single value is being provided.
	 * @param mixed|null $value The value if a single item of data is being added, `null` otherwise.
	 */
	public static function inject($keyOrData, $value = null): void
	{
		if (is_array($keyOrData)) {
			self::$m_injectedData = array_merge(self::$m_injectedData, $keyOrData);
		} else if (is_string($keyOrData)) {
			self::$m_injectedData[$keyOrData] = $value;
		} else {
			throw new TypeError("Argument for parameter \$keyOrData must be a string or array.");
		}
	}

	/**
	 * Specify the layout for the view on top of the rendering stack.
	 *
	 * Call this at the start of your view. Presently, so long as it appears before the first section or stack in the
	 * view it will be fine; however this should be considered an implementation detail and you should place your
	 * layout() call at the start of your view. A view can only have one layout.
	 *
	 * @param string $name The name of the layout view.
	 * @throws ViewNotFoundException
	 */
	public static function layout(string $name): void
	{
		$view = self::currentView();

		if (!$view) {
			throw new LogicException("Can't set a layout when no view is rendering.");
		}

		if (isset($view->m_layout)) {
			throw new LogicException("View {$view->name()} already has the layout {$view->m_layout->name()}.");
		}

		$view->m_layout = new Layout($name);
		self::$m_layoutStack[] = $view->m_layout;
	}

	/**
	 * Include a view in the current view.
	 *
	 * Included views inherit the data from the view that includes them.
	 *
	 * @param string $name The view to include.
	 * @param array $data The data to provide to the view.
	 *
	 * @throws ViewNotFoundException
	 * @throws ViewRenderingException
	 */
	public static function include(string $name, array $data = []): void
	{
		$view = self::currentView();

		if (!$view) {
			throw new LogicException("Can't include a view when no view is rendering.");
		}

		echo (new View($name))->with(array_merge($view->data(), $data))->render();
	}

	/**
	 * Use a view as a component in the current view.
	 *
	 * Components do not inherit the data from the view that uses them. You can't use a component as the content for
	 * another component's slots, but you can use components within the view source of the called component. So this is
	 * not valid:
	 *
	 * ```php
	 * View::component("foo")
	 *      View::component("bar")
	 *      View::endComponent()
	 * View::endComponent()
	 * ```
	 *
	 * But this is:
	 *
	 * view.php:
	 * ```php
	 * View::component("foo")
	 * View::endComponent()
	 * ```
	 *
	 * foo.php component:
	 * ```php
	 * View::component("bar")
	 * View::endComponent()
	 * ```
	 *
	 * A future update may remove this restriction and allow components to be used as content for slots for other
	 * components.
	 *
	 * @param string $name The view to use as a component.
	 * @param array $data The data to provide to the component.
	 *
	 * @throws LogicException if there is no view currently rendering or if there is already a layout component
	 * rendering.
	 */
	public static function component(string $name, array $data = []): void
	{
		$view = self::currentView();

		if (!$view) {
			throw new LogicException("Can't use a component when no view is rendering.");
		}

		if (isset($view->m_currentComponent)) {
			throw new LogicException("Can't nest components.");
		}

		$view->m_currentComponent = $name;
		$view->m_currentComponentData = $data;
		ob_start();
	}

	/**
	 * End the current component's content.
	 *
	 * @throws ViewNotFoundException
	 * @throws ViewRenderingException
	 */
	public static function endComponent(): void
	{
		$view = self::currentView();

		if (!$view) {
			throw new LogicException("Can't use a component when no view is rendering.");
		}

		if (!isset($view->m_currentComponent)) {
			throw new LogicException("endComponent() called with no matching component() call.");
		}

		echo (new View($view->m_currentComponent))
			->with(array_merge(
				$view->m_currentComponentData,
				$view->m_currentComponentSlots,
				["slot" => ob_get_clean(),]
			))
			->render();

		$view->m_currentComponent = null;
		$view->m_currentComponentData = [];

	}

	/**
	 * Provide content for a named slot in a component.
	 *
	 * @param string $name The view to use as a component.
	 */
	public static function slot(string $name): void
	{
		$view = self::currentView();

		if (!$view) {
			throw new LogicException("Can't use a component when no view is rendering.");
		}

		if (!isset($view->m_currentComponent)) {
			throw new LogicException("Can't use a slot without a component.");
		}

		$view->m_currentComponentSlotName = $name;
		ob_start();
	}

	/**
	 * End the current slot's content.
	 *
	 * @throws LogicException if no view is currently rendering or there is no matching slot() call.
	 */
	public static function endSlot(): void
	{
		$view = self::currentView();

		if (!$view) {
			throw new LogicException("Can't use a slot when no view is rendering.");
		}

		if (!isset($view->m_currentComponentSlotName)) {
			throw new LogicException("endSlot() called with no matching slot() call.");
		}

		$view->m_currentComponentSlots[$view->m_currentComponentSlotName] = ob_get_clean();
		$view->m_currentComponentSlotName = null;
	}

	/**
	 * Start producing content for a section in the current view's layout.
	 *
	 * @param string $name The section name.
	 */
	public static function section(string $name): void
	{
		if (!self::isValidSectionName($name)) {
			throw new InvalidArgumentException("'{$name}' is not a valid section name.");
		}

		$layout = self::currentLayout();

		if (!isset($layout)) {
			throw new LogicException("Can't start a section in a view that does not have a layout.");
		}

		if (isset($layout->m_sections[$name])) {
			throw new RuntimeException("Section named {$name} already exists.");
		}

		if (isset($layout->m_currentSection)) {
			throw new LogicException("Sections can't be nested.");
		}

		$layout->m_currentSection = $name;
		ob_start();
	}

	/**
	 * Finish producing content for the current section in the current view's layout.
	 */
	public static function endSection(): void
	{
		$layout = self::currentLayout();

		if (!isset($layout)) {
			throw new LogicException("Can't end a section in a view that does not have a layout.");
		}

		if (!isset($layout->m_currentSection)) {
			throw new RuntimeException("endSection() called with no matching section() call.");
		}

		$layout->m_sections[$layout->m_currentSection] = ob_get_clean();
		$layout->m_currentSection                      = null;
	}

	/**
	 * Check whether some content has been provided for a named section.
	 *
	 * @param string $name The section name.
	 *
	 * @return bool `true` if there is some content, `false` otherwise.
	 */
	public static function hasSection(string $name): bool
	{
		$layout = self::currentLayout();
		return isset($layout) && isset($layout->m_sections[$name]);
	}

	/**
	 * Yield the content of a section defined in a view that uses this view as its layout.
	 *
	 * @param string $name The name of the section that the child view is expected to have provided.
	 */
	public static function yieldSection(string $name): void
	{
		if (!self::isValidSectionName($name)) {
			throw new InvalidArgumentException("{$name} is not a valid section name.");
		}

		$view = self::currentView();

		if (!isset($view)) {
			throw new LogicException("Can't yield a section when not rendering a view.");
		}

		echo $view->m_sections[$name] ?? "";
	}

	/**
	 * Push some content onto a named stack.
	 *
	 * The content enclosed between push() and endPush() will be added to the named stack.
	 *
	 * @param string $name The stack name.
	 */
	public static function push(string $name): void
	{
		if (!self::isValidStackName($name)) {
			throw new InvalidArgumentException("'{$name}' is not a valid stack name.");
		}

		$layout = self::currentLayout();

		if (!isset($layout)) {
			throw new LogicException("Can't push to a stack in a view with no layout.");
		}

		if (isset($layout->currentStack)) {
			throw new LogicException("Can't nest pushes to stacks.");
		}

		$layout->m_currentStack = $name;
		$layout->m_currentStackOnce = false;

		if (!isset($layout->m_stacks[$name])) {
			$layout->m_stacks[$name] = [];
		}

		ob_start();
	}

	/**
	 * Push some content onto a named stack as long as it's not already on the stack.
	 *
	 * The content enclosed between push() and endPush() will be added to the named stack.
	 *
	 * @param string $name The stack name.
	 */
	public static function pushOnce(string $name): void
	{
		self::push($name);
		self::currentLayout()->m_currentStackOnce = true;
	}

	/**
	 * End pushing content onto the current named stack.
	 */
	public static function endPush(): void
	{
		$layout = self::currentLayout();

		if (!isset($layout)) {
			throw new LogicException("Can't end a push to a stack in a view with no layout.");
		}

		if (!isset($layout->m_currentStack)) {
			throw new RuntimeException("endPush() called with no matching push() call.");
		}

		$content = ob_get_clean();

		if (!$layout->m_currentStackOnce || !some($layout->m_stacks[$layout->m_currentStack], function(string $stackItem) use ($content): bool {
			return trim($stackItem) === trim($content);
		})) {
			$layout->m_stacks[$layout->m_currentStack][] = $content;
		}

		$layout->m_currentStack = null;
		$layout->m_currentStackOnce =false;
	}

	/**
	 * Yield the content of a named stack into a layout.
	 *
	 * @param string $name The name of the stack.
	 */
	public static function stack(string $name): void
	{
		if (!self::isValidStackName($name)) {
			throw new InvalidArgumentException("{$name} is not a valid stack name.");
		}

		$view = self::currentView();

		if (!isset($view)) {
			throw new LogicException("Can't output a stack when not rendering a view.");
		}

		if (isset($view->m_stacks[$name])) {
			echo implode("", $view->m_stacks[$name]);
		}
	}

	/**
	 * Add a hidden form element with the current CSRF token to the view.
	 */
	public static function csrf(): void
	{
		echo "<input type=\"hidden\" name=\"_token\" value=\"" . html(WebApplication::instance()->csrf()) . "\" />";
	}

	/**
	 * Fetch the name of the view.
	 * @return string
	 */
	public function name(): string
	{
		return $this->m_name;
	}

	/**
	 * Fetch the data for the view.
	 *
	 * Only the view's own specific data is returned. This does not include the data injected into all views.
	 *
	 * @return array
	 */
	public function data(): array
	{
		return $this->m_data;
	}

	/**
	 * Fetch all the data to provide to the view when rendering.
	 *
	 * @return array The data.
	 */
	protected function allData(): array
	{
		// order ensures view's own data overrides injected data
		return array_merge(self::$m_injectedData, $this->m_data);
	}

	/**
	 * Fluently add some data to the view.
	 *
	 * @param array|string $keyOrData The array of data to add, or the key if a single value is being provided.
	 * @param mixed|null $value The value if a single item of data is being added, `null` otherwise.
	 *
	 * @return $this The View for further method chaining.
	 */
	public function with($keyOrData, $value = null): self
	{
		if (is_array($keyOrData)) {
			$this->m_data = array_merge($this->m_data, $keyOrData);
		} else if (is_string($keyOrData)) {
			$this->m_data[$keyOrData] = $value;
		} else {
			throw new TypeError("Argument for parameter \$keyOrData must be a string or array.");
		}

		return $this;
	}

	/**
	 * Render the view.
	 *
	 * If the view has a layout, any sections in the view will be inserted into the appropriate places in the layout
	 * (assuming it has a matching yield for the section). If the view provides any content not wrapped in a section,
	 * it is output at the end of the layout. This is almost never what you actually want.
	 *
	 * @return string The rendered view.
	 * @throws ViewRenderingException
	 */
	public function render(): string
	{
		self::$m_renderStack[] = $this;
		ob_start();
		$data = $this->allData();

		try {
			// encapsulate the rendering of the view in a lambda so that only the intended data is shared with the view
			if (!(function () use ($data) {
				$app = WebApplication::instance();
				extract($data, EXTR_SKIP);
				return @include $this->m_path;
			})()) {
				ob_end_clean();
				throw new ViewRenderingException($this, "The view could not be rendered.");
			}
		} catch (\Throwable $err) {
			throw new ViewRenderingException($this, "Exception rendering the view {$this->m_name}.", 0, $err);
		}

		$content = ob_get_clean();

		if (isset($this->m_layout)) {
			$content = $this->m_layout->render() . $content;
			array_pop(self::$m_layoutStack);
		}

		array_pop(self::$m_renderStack);
		return $content;
	}

	/**
	 * The HTTP status code.
	 * @return int Always 200 for Views.
	 */
	public function statusCode(): int
	{
		return 200;
	}

	/**
	 * The HTTP content type.
	 * @return string Always "text/html".
	 */
	public function contentType(): string
	{
		return "text/html";
	}

	/**
	 * The HTTP response content.
	 * @return string The HTML for the view.
	 * @throws ViewRenderingException
	 */
	public function content(): string
	{
		return $this->render();
	}

	/**
	 * Send the response.
	 * @throws ViewRenderingException
	 */
	public function send(): void
	{
		http_response_code($this->statusCode());
		header("content-type", $this->contentType());

		foreach ($this->headers() as $header => $value) {
			header($header, $value);
		}

		echo $this->content();
	}
}
