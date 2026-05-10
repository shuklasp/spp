/**
 * Mobile Studio Pro - Architectural Blueprint Registry
 * 
 * This registry allows developers to extend the studio with custom blueprints and layout templates
 * without modifying the core studio engine.
 */

window.MobileBlueprints = {
    // Structural Layouts (Right Sidebar - Inspector)
    layouts: {
        dashboard: (timestamp) => [
            { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'Dashboard' } },
            { id: 't_gv' + timestamp, type: 'grid_view', props: { cols: 2 }, children: [
                { id: 't_c1' + timestamp, type: 'card', props: { text: 'Active Assets' } },
                { id: 't_c2' + timestamp, type: 'card', props: { text: 'System Health' } },
                { id: 't_c3' + timestamp, type: 'card', props: { text: 'Live Traffic' } },
                { id: 't_c4' + timestamp, type: 'card', props: { text: 'Storage' } }
            ]}
        ],
        form: (timestamp) => [
            { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'New Entry' } },
            { id: 't_i1' + timestamp, type: 'input', props: { text: 'Field Label' } },
            { id: 't_bt' + timestamp, type: 'button', props: { text: 'Submit' } }
        ],
        list: (timestamp) => [
            { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'Records List' } },
            { id: 't_ls' + timestamp, type: 'list' }
        ],
        details: (timestamp) => [
            { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'Details View' } },
            { id: 't_im' + timestamp, type: 'image' },
            { id: 't_t1' + timestamp, type: 'text', props: { text: 'Primary Information', padding: 10, fontWeight: 'bold' } },
            { id: 't_t2' + timestamp, type: 'text', props: { text: 'Secondary metadata goes here...', padding: 10, opacity: 0.6 } }
        ],
        split_view: (timestamp) => [
            { id: 't_im' + timestamp, type: 'image', props: { height: 300 } },
            { id: 't_co' + timestamp, type: 'container', props: { padding: 20 }, children: [
                { id: 't_t1' + timestamp, type: 'text', props: { text: 'Section Title', fontSize: 20, fontWeight: 'bold' } },
                { id: 't_t2' + timestamp, type: 'text', props: { text: 'Content details go here...' } }
            ]}
        ],
        centered_card: (timestamp) => [
            { id: 't_sp' + timestamp, type: 'spacer', props: { height: 150 } },
            { id: 't_ca' + timestamp, type: 'card', props: { padding: 30 }, children: [
                { id: 't_t1' + timestamp, type: 'text', props: { text: 'Important Action', textAlign: 'center', fontWeight: 'bold' } },
                { id: 't_bt' + timestamp, type: 'button', props: { text: 'Confirm' } }
            ]}
        ],
        bottom_action: (timestamp) => [
            { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'Scroll Content' } },
            { id: 't_t1' + timestamp, type: 'text', props: { text: 'Scrollable content area...', padding: 20 } },
            { id: 't_bt' + timestamp, type: 'button', props: { text: 'Primary Action', position: 'absolute', bottom: 20 } }
        ]
    },

    // High-Fidelity Blueprints (Left Sidebar - Library)
    blueprints: [
        { 
            id: 'dashboard', 
            name: 'Analytics Dashboard', 
            description: 'MD3-aligned data orchestration layout.',
            template: (timestamp) => [
                { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'Dashboard' } },
                { id: 't_gv' + timestamp, type: 'grid_view', props: { cols: 2 }, children: [
                    { id: 't_c1' + timestamp, type: 'card', props: { text: 'Active Assets' } },
                    { id: 't_c2' + timestamp, type: 'card', props: { text: 'System Health' } },
                    { id: 't_c3' + timestamp, type: 'card', props: { text: 'Live Traffic' } },
                    { id: 't_c4' + timestamp, type: 'card', props: { text: 'Storage' } }
                ]}
            ]
        },
        { 
            id: 'social_feed', 
            name: 'Discovery Feed', 
            description: 'Elite social engagement layout.',
            template: (timestamp) => [
                { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'Discovery' } },
                { id: 't_c1' + timestamp, type: 'card', children: [
                    { id: 't_im1' + timestamp, type: 'image', props: { src: 'https://images.unsplash.com/photo-1682687220742-aba13b6e50ba' } },
                    { id: 't_tx1' + timestamp, type: 'text', props: { text: 'Visualizing the future of mobile engineering.', padding: 15 } }
                ]},
                { id: 't_c2' + timestamp, type: 'card', children: [
                    { id: 't_im2' + timestamp, type: 'image', props: { src: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085' } },
                    { id: 't_tx2' + timestamp, type: 'text', props: { text: 'Elite widget ecosystem in action.', padding: 15 } }
                ]}
            ]
        },
        { 
            id: 'product_details', 
            name: 'Product Details', 
            description: 'High-conversion e-commerce view.',
            template: (timestamp) => [
                { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'Elite Product' } },
                { id: 't_ca' + timestamp, type: 'carousel' },
                { id: 't_tx1' + timestamp, type: 'text', props: { text: 'Flutter Pro Components', padding: 15, fontSize: 24, fontWeight: '700' } },
                { id: 't_tx2' + timestamp, type: 'text', props: { text: '$999.00', padding: 15, color: '#6366f1' } },
                { id: 't_bt' + timestamp, type: 'button', props: { text: 'Add to Cart' } }
            ]
        },
        { 
            id: 'auth', 
            name: 'Modern Auth', 
            description: 'Clean login/signup flow with secure inputs.',
            template: (timestamp) => [
                { id: 't_sp' + timestamp, type: 'spacer', props: { height: 60 } },
                { id: 't_tx1' + timestamp, type: 'text', props: { text: 'Welcome Back', padding: 20, fontSize: 28, fontWeight: '800' } },
                { id: 't_i1' + timestamp, type: 'input', props: { text: 'Username' } },
                { id: 't_i2' + timestamp, type: 'input', props: { text: 'Password' } },
                { id: 't_bt' + timestamp, type: 'button', props: { text: 'Log In' } }
            ]
        },
        { 
            id: 'chat', 
            name: 'Engineering Hub', 
            description: 'Real-time chat layout with message history.',
            template: (timestamp) => [
                { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'Engineering Hub' } },
                { id: 't_ls' + timestamp, type: 'list', children: [
                    { id: 't_tx1' + timestamp, type: 'text', props: { text: 'Team: Systems look stable.', padding: 10 } },
                    { id: 't_tx2' + timestamp, type: 'text', props: { text: 'Lead: Proceed with deployment.', padding: 10, textAlign: 'right' } }
                ]},
                { id: 't_sb' + timestamp, type: 'search_bar', props: { text: 'Type a message...' } }
            ]
        },
        { 
            id: 'profile', 
            name: 'User Profile', 
            description: 'Avatar header and account management list.',
            template: (timestamp) => [
                { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'User Profile' } },
                { id: 't_av' + timestamp, type: 'avatar', props: { size: 80 } },
                { id: 't_t1' + timestamp, type: 'text', props: { text: 'Alex Chen', padding: 10, textAlign: 'center', fontWeight: 'bold' } },
                { id: 't_ls' + timestamp, type: 'list', children: [
                    { id: 't_li1' + timestamp, type: 'action_chip', props: { text: 'Personal Info' } },
                    { id: 't_li2' + timestamp, type: 'action_chip', props: { text: 'Privacy & Security' } }
                ]}
            ]
        },
        { 
            id: 'settings', 
            name: 'App Settings', 
            description: 'System configuration and switches.',
            template: (timestamp) => [
                { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'Settings' } },
                { id: 't_sw1' + timestamp, type: 'switch', props: { text: 'Dark Mode' } },
                { id: 't_sw2' + timestamp, type: 'switch', props: { text: 'Push Notifications' } }
            ]
        },
        { 
            id: 'news_feed', 
            name: 'News Feed', 
            description: 'Article list with featured carousel.',
            template: (timestamp) => [
                { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'World News' } },
                { id: 't_ca' + timestamp, type: 'carousel' },
                { id: 't_ls' + timestamp, type: 'list', children: [
                    { id: 't_c1' + timestamp, type: 'card', props: { text: 'Economy Update: Markets stable.' } },
                    { id: 't_c2' + timestamp, type: 'card', props: { text: 'Tech Trends: AI dominates 2026.' } }
                ]}
            ]
        },
        { 
            id: 'e_commerce', 
            name: 'E-Commerce Gallery', 
            description: 'Product grid with search and filters.',
            template: (timestamp) => [
                { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'Pro Store' } },
                { id: 't_sb' + timestamp, type: 'search_bar' },
                { id: 't_gv' + timestamp, type: 'grid_view', props: { cols: 2 }, children: [
                    { id: 't_p1' + timestamp, type: 'card', children: [{id:'p1_i', type:'image'}, {id:'p1_t', type:'text', props:{text:'Product A', textAlign:'center'}}] },
                    { id: 't_p2' + timestamp, type: 'card', children: [{id:'p2_i', type:'image'}, {id:'p2_t', type:'text', props:{text:'Product B', textAlign:'center'}}] }
                ]}
            ]
        },
        { 
            id: 'onboarding', 
            name: 'Onboarding Flow', 
            description: 'Professional welcome sequence.',
            template: (timestamp) => [
                { id: 't_sp' + timestamp, type: 'spacer', props: { height: 100 } },
                { id: 't_im' + timestamp, type: 'image', props: { borderRadius: 100 } },
                { id: 't_t1' + timestamp, type: 'text', props: { text: 'Welcome to SPP Mobile', fontSize: 24, textAlign: 'center', fontWeight: '900' } },
                { id: 't_t2' + timestamp, type: 'text', props: { text: 'The future of visual engineering starts here.', textAlign: 'center', opacity: 0.6 } },
                { id: 't_bt' + timestamp, type: 'button', props: { text: 'Get Started', margin: 40 } }
            ]
        },
        { 
            id: 'analytics_pro', 
            name: 'Analytics Pro', 
            description: 'Advanced data cards and charts.',
            template: (timestamp) => [
                { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'System Analytics' } },
                { id: 't_gv' + timestamp, type: 'grid_view', props: { cols: 2 }, children: [
                    { id: 't_s1' + timestamp, type: 'card', props: { text: '98% Uptime' } },
                    { id: 't_s2' + timestamp, type: 'card', props: { text: '1.2k Sessions' } }
                ]}
            ]
        },
        { 
            id: 'checkout', 
            name: 'Secure Checkout', 
            description: 'Order summary and payment orchestration.',
            template: (timestamp) => [
                { id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'Checkout' } },
                { id: 't_dt' + timestamp, type: 'data_table' },
                { id: 't_tx' + timestamp, type: 'text', props: { text: 'Total: $1,240.00', padding: 20, textAlign: 'right', fontWeight: 'bold' } },
                { id: 't_bt' + timestamp, type: 'button', props: { text: 'Place Order' } }
            ]
        },
        { 
            id: 'article', 
            name: 'Rich Article', 
            description: 'Typography-optimized content layout.',
            template: (timestamp) => [
                { id: 't_im' + timestamp, type: 'image', props: { src: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa' } },
                { id: 't_t1' + timestamp, type: 'text', props: { text: 'The Future of Mobile Studio', fontSize: 24, fontWeight: '900', padding: 15 } },
                { id: 't_t2' + timestamp, type: 'text', props: { text: 'Exploring the limitless possibilities of visual mobile engineering...', padding: 15, opacity: 0.8 } }
            ]
        }
    ]
};
