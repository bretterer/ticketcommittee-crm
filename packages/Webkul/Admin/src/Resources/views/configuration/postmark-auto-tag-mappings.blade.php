@php
    $fieldName = $field->getNameField();
    $storedValue = system_config()->getConfigData($field->getNameKey());
    $mappings = [];

    if (!empty($storedValue)) {
        $decoded = json_decode($storedValue, true);
        if (is_array($decoded)) {
            $mappings = $decoded;
        }
    }

    $tags = \Webkul\Tag\Models\Tag::all();
@endphp

<input
    type="hidden"
    name="keys[]"
    value="{{ json_encode($child) }}"
/>

<div class="mb-4">
    <v-auto-tag-mappings
        field-name="{{ $fieldName }}"
        :initial-mappings='@json($mappings)'
        :available-tags='@json($tags)'
    ></v-auto-tag-mappings>
</div>

@pushOnce('scripts')
    <script type="text/x-template" id="v-auto-tag-mappings-template">
        <div class="flex flex-col gap-4">
            <!-- Label -->
            <label class="flex items-center gap-1 text-xs font-medium text-gray-800 dark:text-white">
                @lang('admin::app.configuration.index.email.postmark.general.auto-tag-mappings')
            </label>

            <!-- Hidden input to store JSON data -->
            <input
                type="hidden"
                :name="fieldName"
                :value="JSON.stringify(mappings)"
            />

            <!-- Mappings List -->
            <div class="flex flex-col gap-3">
                <div
                    v-for="(mapping, index) in mappings"
                    :key="index"
                    class="flex items-center gap-3"
                >
                    <!-- Email Input -->
                    <div class="w-1/4">
                        <input
                            type="text"
                            v-model="mapping.email"
                            class="w-full rounded-md border border-gray-200 px-3 py-2.5 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                            placeholder="@lang('admin::app.configuration.index.email.postmark.general.email-placeholder')"
                        />
                    </div>

                    <!-- Display Name Input -->
                    <div class="w-1/4">
                        <input
                            type="text"
                            v-model="mapping.name"
                            class="w-full rounded-md border border-gray-200 px-3 py-2.5 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                            placeholder="@lang('admin::app.configuration.index.email.postmark.general.display-name-placeholder')"
                        />
                    </div>

                    <!-- Arrow -->
                    <span class="text-gray-400">→</span>

                    <!-- Tag Selector -->
                    <div class="flex-1 relative">
                        <div
                            class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2.5 text-sm transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-gray-400 cursor-pointer"
                            @click="toggleDropdown(index)"
                        >
                            <span
                                v-if="mapping.tag_id && getTagById(mapping.tag_id)"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="h-3 w-3 rounded-full"
                                    :style="{ backgroundColor: getTagById(mapping.tag_id)?.color || '#546E7A' }"
                                ></span>
                                <span class="text-gray-800 dark:text-gray-300">@{{ getTagById(mapping.tag_id)?.name }}</span>
                            </span>
                            <span v-else class="text-gray-400">
                                @lang('admin::app.configuration.index.email.postmark.general.select-tag')
                            </span>
                            <span class="icon-down-arrow text-xl"></span>
                        </div>

                        <!-- Tag Dropdown -->
                        <div
                            v-if="activeDropdown === index"
                            class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                        >
                            <!-- Search Input -->
                            <div class="p-2 border-b border-gray-200 dark:border-gray-800">
                                <input
                                    type="text"
                                    v-model="searchTerm"
                                    class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    placeholder="@lang('admin::app.components.tags.index.placeholder')"
                                    @click.stop
                                />
                            </div>

                            <!-- Tag List -->
                            <ul class="max-h-48 overflow-y-auto">
                                <li
                                    v-for="tag in filteredTags"
                                    :key="tag.id"
                                    class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-gray-800 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                    @click="selectTag(index, tag)"
                                >
                                    <span
                                        class="h-3 w-3 rounded-full"
                                        :style="{ backgroundColor: tag.color || '#546E7A' }"
                                    ></span>
                                    @{{ tag.name }}
                                </li>

                                <li
                                    v-if="filteredTags.length === 0 && searchTerm.length >= 2"
                                    class="px-3 py-2 text-sm"
                                >
                                    <button
                                        type="button"
                                        class="flex items-center gap-2 text-brandColor hover:underline"
                                        @click.stop="createTag(index)"
                                    >
                                        <i class="icon-add text-md"></i>
                                        @lang('admin::app.configuration.index.email.postmark.general.create-tag') "@{{ searchTerm }}"
                                    </button>
                                </li>

                                <li
                                    v-if="filteredTags.length === 0 && searchTerm.length < 2"
                                    class="px-3 py-2 text-sm text-gray-400"
                                >
                                    @lang('admin::app.configuration.index.email.postmark.general.type-to-search')
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Remove Button -->
                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-md text-gray-600 transition-all hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="removeMapping(index)"
                    >
                        <i class="icon-delete text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Add Button -->
            <button
                type="button"
                class="flex items-center gap-2 text-sm text-brandColor hover:underline"
                @click="addMapping"
            >
                <i class="icon-add text-md"></i>
                @lang('admin::app.configuration.index.email.postmark.general.add-mapping')
            </button>

            <!-- Info Text -->
            <p class="text-xs italic text-gray-600 dark:text-gray-300">
                @lang('admin::app.configuration.index.email.postmark.general.auto-tag-mappings-info')
            </p>
        </div>
    </script>

    <script type="module">
        app.component('v-auto-tag-mappings', {
            template: '#v-auto-tag-mappings-template',

            props: {
                fieldName: {
                    type: String,
                    required: true,
                },
                initialMappings: {
                    type: Array,
                    default: () => [],
                },
                availableTags: {
                    type: Array,
                    default: () => [],
                },
            },

            data() {
                return {
                    mappings: this.initialMappings.length ? this.initialMappings : [],
                    tags: this.availableTags,
                    activeDropdown: null,
                    searchTerm: '',
                    isCreatingTag: false,
                    backgroundColors: [
                        '#FEE2E2', '#FFEDD5', '#FEF3C7', '#FEF9C3', '#ECFCCB', '#DCFCE7',
                    ],
                };
            },

            computed: {
                filteredTags() {
                    if (!this.searchTerm) {
                        return this.tags;
                    }
                    const term = this.searchTerm.toLowerCase();
                    return this.tags.filter(tag =>
                        tag.name.toLowerCase().includes(term)
                    );
                },
            },

            mounted() {
                document.addEventListener('click', this.closeDropdowns);
            },

            beforeUnmount() {
                document.removeEventListener('click', this.closeDropdowns);
            },

            methods: {
                addMapping() {
                    this.mappings.push({ email: '', tag_id: null, name: '' });
                },

                removeMapping(index) {
                    this.mappings.splice(index, 1);
                },

                toggleDropdown(index) {
                    if (this.activeDropdown === index) {
                        this.activeDropdown = null;
                    } else {
                        this.activeDropdown = index;
                        this.searchTerm = '';
                    }
                },

                closeDropdowns(event) {
                    if (!event.target.closest('.relative')) {
                        this.activeDropdown = null;
                    }
                },

                selectTag(index, tag) {
                    this.mappings[index].tag_id = tag.id;
                    this.activeDropdown = null;
                    this.searchTerm = '';
                },

                getTagById(tagId) {
                    return this.tags.find(tag => tag.id === tagId);
                },

                createTag(index) {
                    if (this.isCreatingTag || this.searchTerm.length < 2) {
                        return;
                    }

                    this.isCreatingTag = true;

                    const randomColor = this.backgroundColors[
                        Math.floor(Math.random() * this.backgroundColors.length)
                    ];

                    this.$axios.post("{{ route('admin.settings.tags.store') }}", {
                        name: this.searchTerm,
                        color: randomColor,
                    })
                    .then(response => {
                        const newTag = response.data.data;
                        this.tags.push(newTag);
                        this.mappings[index].tag_id = newTag.id;
                        this.activeDropdown = null;
                        this.searchTerm = '';
                        this.isCreatingTag = false;

                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message
                        });
                    })
                    .catch(error => {
                        this.isCreatingTag = false;
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Failed to create tag'
                        });
                    });
                },
            },
        });
    </script>
@endPushOnce
