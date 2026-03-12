<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Module_Schema {

    public function __construct() {
        add_action( 'wp_head', [ $this, 'output_schema' ], 2 );
    }

    public function output_schema(): void {
        $graph = [];

        // Website schema (always)
        $graph[] = $this->get_website_schema();

        // WebPage schema (always on singular)
        if ( is_singular() ) {
            $graph[] = $this->get_webpage_schema();
            $graph[] = $this->get_breadcrumb_schema();

            // Post-specific schema
            $post_id = get_the_ID();
            $type    = get_post_meta( $post_id, '_msh_schema_type', true );
            $custom  = get_post_meta( $post_id, '_msh_schema_data', true );

            if ( $custom && is_array( $custom ) ) {
                $graph[] = $custom;
            } elseif ( $type ) {
                $auto = $this->auto_generate( $type, $post_id );
                if ( $auto ) $graph[] = $auto;
            } else {
                // Default: Article for posts, WebPage for pages
                $post_type = get_post_type( $post_id );
                if ( $post_type === 'post' ) {
                    $graph[] = $this->auto_generate( 'Article', $post_id );
                }
            }
        }

        if ( is_front_page() ) {
            $org = $this->get_organization_schema();
            if ( $org ) $graph[] = $org;
        }

        // Filter out nulls
        $graph = array_filter( $graph );

        if ( empty( $graph ) ) return;

        $output = [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ];

        $output = apply_filters( 'msh_schema_graph', $output );

        echo '<script type="application/ld+json">' . wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
    }

    private function get_website_schema(): array {
        $schema = [
            '@type' => 'WebSite',
            '@id'   => home_url( '/#website' ),
            'url'   => home_url( '/' ),
            'name'  => get_bloginfo( 'name' ),
        ];

        $tagline = get_bloginfo( 'description' );
        if ( $tagline ) {
            $schema['description'] = $tagline;
        }

        // Sitelinks search box
        $schema['potentialAction'] = [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'        => 'EntryPoint',
                'urlTemplate'  => home_url( '/?s={search_term_string}' ),
            ],
            'query-input' => 'required name=search_term_string',
        ];

        return $schema;
    }

    private function get_webpage_schema(): array {
        $post_id = get_the_ID();
        return [
            '@type'           => 'WebPage',
            '@id'             => get_permalink( $post_id ) . '#webpage',
            'url'             => get_permalink( $post_id ),
            'name'            => get_the_title( $post_id ),
            'isPartOf'        => [ '@id' => home_url( '/#website' ) ],
            'datePublished'   => get_the_date( 'c', $post_id ),
            'dateModified'    => get_the_modified_date( 'c', $post_id ),
            'description'     => get_post_meta( $post_id, '_msh_description', true ) ?: wp_trim_words( get_the_excerpt( $post_id ), 30 ),
            'inLanguage'      => get_locale(),
        ];
    }

    private function get_breadcrumb_schema(): ?array {
        if ( is_front_page() ) return null;

        $items = [];
        $pos   = 1;

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => 'Home',
            'item'     => home_url( '/' ),
        ];

        if ( is_singular( 'post' ) ) {
            $cats = get_the_category();
            if ( ! empty( $cats ) ) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => $cats[0]->name,
                    'item'     => get_category_link( $cats[0]->term_id ),
                ];
            }
        }

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos,
            'name'     => get_the_title(),
        ];

        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => get_permalink() . '#breadcrumb',
            'itemListElement' => $items,
        ];
    }

    private function get_organization_schema(): ?array {
        $settings = get_option( 'msh_local_seo_settings', [] );
        if ( empty( $settings ) ) {
            return [
                '@type' => 'Organization',
                '@id'   => home_url( '/#organization' ),
                'name'  => get_bloginfo( 'name' ),
                'url'   => home_url( '/' ),
            ];
        }

        $schema = [
            '@type' => $settings['business_type'] ?? 'Organization',
            '@id'   => home_url( '/#organization' ),
            'name'  => $settings['business_name'] ?? get_bloginfo( 'name' ),
            'url'   => home_url( '/' ),
        ];

        if ( ! empty( $settings['logo'] ) ) {
            $schema['logo'] = [
                '@type'      => 'ImageObject',
                '@id'        => home_url( '/#logo' ),
                'url'        => $settings['logo'],
                'contentUrl' => $settings['logo'],
            ];
            $schema['image'] = [ '@id' => home_url( '/#logo' ) ];
        }

        if ( ! empty( $settings['phone'] ) ) {
            $schema['telephone'] = $settings['phone'];
        }
        if ( ! empty( $settings['email'] ) ) {
            $schema['email'] = $settings['email'];
        }

        if ( ! empty( $settings['address'] ) ) {
            $schema['address'] = [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $settings['address']['street'] ?? '',
                'addressLocality' => $settings['address']['city'] ?? '',
                'addressRegion'   => $settings['address']['state'] ?? '',
                'postalCode'      => $settings['address']['zip'] ?? '',
                'addressCountry'  => $settings['address']['country'] ?? '',
            ];
        }

        $social_profiles = array_filter( [
            $settings['facebook'] ?? '',
            $settings['twitter'] ?? '',
            $settings['linkedin'] ?? '',
            $settings['instagram'] ?? '',
            $settings['youtube'] ?? '',
        ] );
        if ( ! empty( $social_profiles ) ) {
            $schema['sameAs'] = $social_profiles;
        }

        return $schema;
    }

    public function auto_generate( string $type, int $post_id ): ?array {
        $post = get_post( $post_id );
        if ( ! $post ) return null;

        switch ( $type ) {
            case 'Article':
            case 'BlogPosting':
            case 'NewsArticle':
                return $this->article_schema( $type, $post );
            case 'FAQ':
                return $this->faq_schema( $post );
            case 'HowTo':
                return $this->howto_schema( $post );
            case 'Product':
                return $this->product_schema( $post );
            case 'LocalBusiness':
                return $this->local_business_schema();
            case 'Event':
                return $this->event_schema( $post );
            case 'Recipe':
                return $this->recipe_schema( $post );
            case 'Course':
                return $this->course_schema( $post );
            case 'SoftwareApplication':
                return $this->software_schema( $post );
            case 'Video':
            case 'VideoObject':
                return $this->video_schema( $post );
            case 'Review':
                return $this->review_schema( $post );
            case 'Person':
                return $this->person_schema( $post );
            case 'JobPosting':
                return $this->job_schema( $post );
            case 'Book':
                return $this->book_schema( $post );
            case 'Service':
                return $this->service_schema( $post );
            default:
                return null;
        }
    }

    private function article_schema( string $type, \WP_Post $post ): array {
        $schema = [
            '@type'            => $type,
            '@id'              => get_permalink( $post->ID ) . '#article',
            'headline'         => $post->post_title,
            'datePublished'    => get_the_date( 'c', $post ),
            'dateModified'     => get_the_modified_date( 'c', $post ),
            'mainEntityOfPage' => [ '@id' => get_permalink( $post->ID ) . '#webpage' ],
            'wordCount'        => str_word_count( wp_strip_all_tags( $post->post_content ) ),
            'inLanguage'       => get_locale(),
            'author'           => [
                '@type' => 'Person',
                'name'  => get_the_author_meta( 'display_name', $post->post_author ),
                'url'   => get_author_posts_url( $post->post_author ),
            ],
            'publisher' => [ '@id' => home_url( '/#organization' ) ],
        ];

        $thumb = get_the_post_thumbnail_url( $post->ID, 'full' );
        if ( $thumb ) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url'   => $thumb,
            ];
        }

        $desc = get_post_meta( $post->ID, '_msh_description', true );
        if ( $desc ) {
            $schema['description'] = $desc;
        }

        return $schema;
    }

    private function faq_schema( \WP_Post $post ): ?array {
        $faq_data = get_post_meta( $post->ID, '_msh_schema_data', true );
        if ( ! $faq_data || ! isset( $faq_data['questions'] ) ) return null;

        $items = [];
        foreach ( $faq_data['questions'] as $q ) {
            $items[] = [
                '@type'          => 'Question',
                'name'           => $q['question'] ?? '',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $q['answer'] ?? '',
                ],
            ];
        }

        return [
            '@type'      => 'FAQPage',
            '@id'        => get_permalink( $post->ID ) . '#faq',
            'mainEntity' => $items,
        ];
    }

    private function howto_schema( \WP_Post $post ): ?array {
        $data = get_post_meta( $post->ID, '_msh_schema_data', true );
        if ( ! $data || ! isset( $data['steps'] ) ) return null;

        $steps = [];
        $pos = 1;
        foreach ( $data['steps'] as $s ) {
            $steps[] = [
                '@type'    => 'HowToStep',
                'position' => $pos++,
                'name'     => $s['name'] ?? '',
                'text'     => $s['text'] ?? '',
            ];
        }

        return [
            '@type'       => 'HowTo',
            '@id'         => get_permalink( $post->ID ) . '#howto',
            'name'        => $data['name'] ?? $post->post_title,
            'description' => $data['description'] ?? '',
            'step'        => $steps,
        ];
    }

    private function product_schema( \WP_Post $post ): array {
        $data = get_post_meta( $post->ID, '_msh_schema_data', true ) ?: [];
        return [
            '@type'       => 'Product',
            '@id'         => get_permalink( $post->ID ) . '#product',
            'name'        => $data['name'] ?? $post->post_title,
            'description' => $data['description'] ?? '',
        ];
    }

    private function local_business_schema(): ?array {
        $settings = get_option( 'msh_local_seo_settings', [] );
        return ! empty( $settings ) ? $this->get_organization_schema() : null;
    }

    private function event_schema( \WP_Post $post ): array {
        $data = get_post_meta( $post->ID, '_msh_schema_data', true ) ?: [];
        return [
            '@type'     => 'Event',
            '@id'       => get_permalink( $post->ID ) . '#event',
            'name'      => $data['name'] ?? $post->post_title,
            'startDate' => $data['startDate'] ?? '',
            'endDate'   => $data['endDate'] ?? '',
        ];
    }

    private function recipe_schema( \WP_Post $post ): array {
        $data = get_post_meta( $post->ID, '_msh_schema_data', true ) ?: [];
        return [ '@type' => 'Recipe', '@id' => get_permalink( $post->ID ) . '#recipe', 'name' => $data['name'] ?? $post->post_title ];
    }

    private function course_schema( \WP_Post $post ): array {
        $data = get_post_meta( $post->ID, '_msh_schema_data', true ) ?: [];
        return [ '@type' => 'Course', '@id' => get_permalink( $post->ID ) . '#course', 'name' => $data['name'] ?? $post->post_title ];
    }

    private function software_schema( \WP_Post $post ): array {
        $data = get_post_meta( $post->ID, '_msh_schema_data', true ) ?: [];
        return [ '@type' => 'SoftwareApplication', '@id' => get_permalink( $post->ID ) . '#software', 'name' => $data['name'] ?? $post->post_title ];
    }

    private function video_schema( \WP_Post $post ): array {
        $data = get_post_meta( $post->ID, '_msh_schema_data', true ) ?: [];
        return [ '@type' => 'VideoObject', '@id' => get_permalink( $post->ID ) . '#video', 'name' => $data['name'] ?? $post->post_title ];
    }

    private function review_schema( \WP_Post $post ): array {
        $data = get_post_meta( $post->ID, '_msh_schema_data', true ) ?: [];
        return [ '@type' => 'Review', '@id' => get_permalink( $post->ID ) . '#review', 'name' => $data['name'] ?? $post->post_title ];
    }

    private function person_schema( \WP_Post $post ): array {
        $data = get_post_meta( $post->ID, '_msh_schema_data', true ) ?: [];
        return [ '@type' => 'Person', '@id' => get_permalink( $post->ID ) . '#person', 'name' => $data['name'] ?? $post->post_title ];
    }

    private function job_schema( \WP_Post $post ): array {
        $data = get_post_meta( $post->ID, '_msh_schema_data', true ) ?: [];
        return [ '@type' => 'JobPosting', '@id' => get_permalink( $post->ID ) . '#job', 'title' => $data['title'] ?? $post->post_title ];
    }

    private function book_schema( \WP_Post $post ): array {
        $data = get_post_meta( $post->ID, '_msh_schema_data', true ) ?: [];
        return [ '@type' => 'Book', '@id' => get_permalink( $post->ID ) . '#book', 'name' => $data['name'] ?? $post->post_title ];
    }

    private function service_schema( \WP_Post $post ): array {
        $data = get_post_meta( $post->ID, '_msh_schema_data', true ) ?: [];
        return [ '@type' => 'Service', '@id' => get_permalink( $post->ID ) . '#service', 'name' => $data['name'] ?? $post->post_title ];
    }

    public static function get_available_types(): array {
        return [
            'Article'              => 'Article',
            'BlogPosting'          => 'Blog Post',
            'NewsArticle'          => 'News Article',
            'FAQ'                  => 'FAQ Page',
            'HowTo'                => 'How-To',
            'Product'              => 'Product',
            'Review'               => 'Review',
            'Recipe'               => 'Recipe',
            'Event'                => 'Event',
            'Course'               => 'Course',
            'Book'                 => 'Book',
            'JobPosting'           => 'Job Posting',
            'LocalBusiness'        => 'Local Business',
            'Person'               => 'Person',
            'Service'              => 'Service',
            'SoftwareApplication'  => 'Software Application',
            'VideoObject'          => 'Video',
            'MusicRecording'       => 'Music',
            'Dataset'              => 'Dataset',
            'Movie'                => 'Movie',
            'Restaurant'           => 'Restaurant',
            'MedicalCondition'     => 'Medical Condition',
            'CollectionPage'       => 'Collection Page',
            'ProfilePage'          => 'Profile Page',
            'RealEstateListing'    => 'Real Estate Listing',
        ];
    }
}
