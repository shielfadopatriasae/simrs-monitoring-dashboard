import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';

// A simple SVG Sparkline component
const Sparkline = ({ data }) => {
    if (!data || data.length === 0) return <div className="h-12 w-full opacity-50">Menunggu data...</div>;
    
    const maxVal = Math.max(...data, 100); // Minimum scale 100ms
    const minVal = 0;
    const width = 200;
    const height = 40;
    
    const points = data.map((val, i) => {
        const x = (i / (Math.max(data.length - 1, 1))) * width;
        const y = height - ((val - minVal) / (maxVal - minVal)) * height;
        return `${x},${y}`;
    }).join(' ');

    return (
        <div className="h-12 w-full flex items-end relative overflow-hidden">
            <svg viewBox={`0 0 ${width} ${height}`} className="w-full h-full preserve-3d" preserveAspectRatio="none">
                <polyline 
                    fill="none" 
                    stroke="currentColor" 
                    strokeWidth="2" 
                    strokeLinecap="round" 
                    strokeLinejoin="round" 
                    points={points} 
                    className="text-blue-500 dark:text-cyan-400 drop-shadow-md"
                />
            </svg>
        </div>
    );
};

// Base64 short beep sound
const alertSoundUrl = 'data:audio/mp3;base64,SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA//OEAAAAAAAAAAAAAAAAAAAAAAAASW5mbwAAAA8AAAAOAAADeQAJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJGRkZGRkZGRkZGRkZGRkZGRkZGRkZGRkZGRkZKSkpKSkpKSkpKSkpKSkpKSkpKSkpKSkpKSkpNTU1NTU1NTU1NTU1NTU1NTU1NTU1NTU1NTU1QkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCUVFRUVFRUVFRUVFRUVFRUVFRUVFRUVFRUVFRYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhcXFxcXFxcXFxcXFxcXFxcXFxcXFxcXFxcXFxeXl5eXl5eXl5eXl5eXl5eXl5eXl5eXl5eXl5hYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZqampqampqampqampqampqampqampqampqampqnJycnJycnJycnJycnJycnJycnJycnJycnJycvLy8vLy8vLy8vLy8vLy8vLy8vLy8vLy8vLy8zMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzM2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY5OTk5OTk5OTk5OTk5OTk5OTk5OTk5OTk5OTk6Ojo6Ojo6Ojo6Ojo6Ojo6Ojo6Ojo6Ojo6Ojo8PDw8PDw8PDw8PDw8PDw8PDw8PDw8PDw8PDw+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4/Pz8/Pz8/Pz8/Pz8/Pz8/Pz8/Pz8/Pz8/Pz8AAAAATGF2YzU4LjEzNAAAAAAAAAAAAAAAACQDAAAAAAAAAAAADgAAA3n4hX+sAAAAAAAAAAAAAAAAAAAAAP/zxgAAABwBEIAAAABKAAAFAAAAAA8AAAA0AQAJAGAAUQAQAGoAYQAEH/78z/z///j4z/z///z/M///4zMz/z////8/M//M/M///MwAD3gADx8MAB5+YAADQAEBAQDAwMAAFAABEQgICAQFAwMAAIAAAQQH4gQAAQEBBAQD/4A//78z/z///j4z/z///z/M///4zMz/z////8/M//M/M///MwAD3gADx8MAB5+YAADQAEBAQDAwMAAFAABEQgICAQFAwMAAIAAAQQH4gQAAQEBBAQD/4A//74z/z///j4z/z///z/M///4zMz/z////8/M//M/M///MwAD3gADx8MAB5+YAADQAEBAQDAwMAAFAABEQgICAQFAwMAAIAAAQQH4gQAAQEBBAQD/4A//74z/z///j4z/z///z/M///4zMz/z////8/M//M/M///MwAD3gADx8MAB5+YAADQAEBAQDAwMAAFAABEQgICAQFAwMAAIAAAQQH4gQAAQEBBAQD/4A';

export default function Dashboard({ endpoints }) {
    const [statusData, setStatusData] = useState({});
    const [loading, setLoading] = useState(true);
    // History array to store past 15 latency values for each endpoint
    const [latencyHistory, setLatencyHistory] = useState({});
    
    const audioRef = useRef(null);

    useEffect(() => {
        // Initialize audio
        audioRef.current = new Audio(alertSoundUrl);
        audioRef.current.volume = 0.5;
    }, []);

    const updateHistory = (id, result) => {
        setLatencyHistory(prev => {
            const currentHistory = prev[id] || [];
            const newHistory = [...currentHistory, result.latency || 0].slice(-15); // Keep last 15 points
            return { ...prev, [id]: newHistory };
        });
    };

    const playAlert = () => {
        if (audioRef.current) {
            audioRef.current.play().catch(e => console.error("Audio play failed:", e));
        }
    };

    const checkAndAlert = (newStatusData) => {
        // Alert hanya berbunyi untuk ERROR dan OFFLINE (bukan WARNING)
        let isCritical = false;
        Object.values(newStatusData).forEach(result => {
            if (result.status === 'ERROR' || result.status === 'OFFLINE') {
                isCritical = true;
            }
        });
        if (isCritical) {
            playAlert();
        }
    };

    const pingAll = async () => {
        setLoading(true);
        try {
            const res = await axios.get(route('api.ping'));
            const newData = res.data;
            setStatusData(newData);
            
            Object.keys(newData).forEach(id => {
                updateHistory(id, newData[id]);
            });
            
            checkAndAlert(newData);
        } catch (error) {
            console.error("Failed to ping endpoints", error);
        }
        setLoading(false);
    };

    const pingSingle = async (id) => {
        try {
            const res = await axios.get(route('api.ping', { id }));
            const result = res.data[id];
            
            setStatusData(prev => {
                const updated = { ...prev, [id]: result };
                checkAndAlert(updated);
                return updated;
            });
            
            updateHistory(id, result);
        } catch (error) {
            console.error(`Failed to ping ${id}`, error);
        }
    };

    useEffect(() => {
        pingAll();
        // Auto refresh every 30 seconds
        const interval = setInterval(() => {
            pingAll();
        }, 30000);
        return () => clearInterval(interval);
    }, []);

    // Derived stats
    const total = Object.keys(endpoints).length;
    let onlineCount = 0;
    let warningCount = 0;
    let errorCount = 0;
    Object.values(statusData).forEach(item => {
        if (item.status === 'ONLINE') onlineCount++;
        else if (item.status === 'WARNING' || item.status === 'NOT_FOUND') warningCount++;
        else errorCount++;
    });

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Dashboard Monitoring SIMRS
                </h2>
            }
        >
            <Head title="Dashboard Monitoring" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    
                    {/* Welcome Banner */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-slate-800/80 dark:backdrop-blur-xl border border-transparent dark:border-slate-700">
                        <div className="p-6 text-gray-900 dark:text-gray-100 flex justify-between items-center">
                            <div>
                                <h3 className="text-lg font-bold">Portal Monitoring API BPJS & KEMENKES</h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Real-time Telemetry for Uninterrupted Healthcare Integration</p>
                            </div>
                            <button 
                                onClick={pingAll}
                                disabled={loading}
                                className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors disabled:opacity-50 flex items-center gap-2"
                            >
                                {loading && (
                                    <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                )}
                                {loading ? 'Memeriksa...' : 'Refresh Semua'}
                            </button>
                        </div>
                    </div>

                    {/* Stats */}
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div className="bg-white dark:bg-slate-800/80 dark:backdrop-blur-xl p-6 rounded-lg shadow-sm border border-transparent dark:border-slate-700">
                            <h4 className="text-sm font-semibold text-gray-500 dark:text-gray-400">Total Endpoint</h4>
                            <p className="text-3xl font-bold text-gray-900 dark:text-white">{total}</p>
                        </div>
                        <div className="bg-white dark:bg-slate-800/80 dark:backdrop-blur-xl p-6 rounded-lg shadow-sm border border-transparent dark:border-green-500/30">
                            <h4 className="text-sm font-semibold text-gray-500 dark:text-gray-400">🟢 Online</h4>
                            <p className="text-3xl font-bold text-green-600 dark:text-green-400">{onlineCount}</p>
                        </div>
                        <div className="bg-white dark:bg-slate-800/80 dark:backdrop-blur-xl p-6 rounded-lg shadow-sm border border-transparent dark:border-yellow-500/30">
                            <h4 className="text-sm font-semibold text-gray-500 dark:text-gray-400">🟡 Warning</h4>
                            <p className="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{warningCount}</p>
                        </div>
                        <div className="bg-white dark:bg-slate-800/80 dark:backdrop-blur-xl p-6 rounded-lg shadow-sm border border-transparent dark:border-red-500/30">
                            <h4 className="text-sm font-semibold text-gray-500 dark:text-gray-400">🔴 Error / Offline</h4>
                            <p className="text-3xl font-bold text-red-600 dark:text-red-400">{errorCount}</p>
                        </div>
                    </div>

                    {/* Endpoints Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {Object.entries(endpoints).map(([id, config]) => {
                            const data = statusData[id] || {};
                            const history = latencyHistory[id] || [];

                            const isOnline    = data.status === 'ONLINE';
                            const isWarning   = data.status === 'WARNING';
                            const isNotFound  = data.status === 'NOT_FOUND';
                            const isError     = data.status === 'ERROR';
                            const isOffline   = data.status === 'OFFLINE';
                            const isCritical  = isError || isOffline;

                            // Warna border kartu
                            const cardBorder = isOnline
                                ? 'border-gray-200 dark:border-green-500/50 shadow-[0_0_15px_rgba(34,197,94,0.1)] hover:shadow-[0_0_20px_rgba(34,197,94,0.2)]'
                                : isWarning
                                ? 'border-yellow-300 dark:border-yellow-500/60 shadow-[0_0_15px_rgba(234,179,8,0.15)] hover:shadow-[0_0_20px_rgba(234,179,8,0.25)]'
                                : isNotFound
                                ? 'border-orange-300 dark:border-orange-500/60 shadow-[0_0_15px_rgba(249,115,22,0.15)] hover:shadow-[0_0_20px_rgba(249,115,22,0.25)]'
                                : isCritical
                                ? 'border-red-300 dark:border-red-500 shadow-[0_0_15px_rgba(239,68,68,0.2)] hover:shadow-[0_0_20px_rgba(239,68,68,0.3)] animate-pulse'
                                : 'border-gray-200 dark:border-slate-700';

                            // Warna & teks badge status
                            const badgeStyle = isOnline
                                ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400 dark:shadow-[0_0_10px_rgba(34,197,94,0.4)]'
                                : isWarning
                                ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-300 dark:shadow-[0_0_10px_rgba(234,179,8,0.4)]'
                                : isNotFound
                                ? 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300 dark:shadow-[0_0_10px_rgba(249,115,22,0.4)]'
                                : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400 dark:shadow-[0_0_10px_rgba(239,68,68,0.4)]';

                            const dotColor = isOnline ? 'bg-green-500'
                                : isWarning ? 'bg-yellow-400'
                                : isNotFound ? 'bg-orange-500'
                                : 'bg-red-500';

                            // Warna teks HTTP Code
                            const codeColor = isOnline
                                ? 'text-green-600 dark:text-green-400'
                                : isWarning
                                ? 'text-yellow-600 dark:text-yellow-400'
                                : isNotFound
                                ? 'text-orange-600 dark:text-orange-400'
                                : 'text-red-600 dark:text-red-400';

                            // Warna message box
                            const msgBoxStyle = isWarning
                                ? 'mt-3 p-2 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded text-xs text-yellow-700 dark:text-yellow-300 font-mono overflow-hidden text-ellipsis whitespace-nowrap'
                                : isNotFound
                                ? 'mt-3 p-2 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-700 rounded text-xs text-orange-700 dark:text-orange-300 font-mono overflow-hidden text-ellipsis whitespace-nowrap'
                                : 'mt-3 p-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-xs text-red-600 dark:text-red-400 font-mono overflow-hidden text-ellipsis whitespace-nowrap';

                            // Label badge
                            const badgeLabel = isOnline ? 'ONLINE'
                                : isWarning ? '⚠ WARNING'
                                : isNotFound ? '🔎 NOT FOUND'
                                : isOffline ? '● OFFLINE'
                                : isError ? '✕ ERROR'
                                : data.status;

                            return (
                                <div key={id} className={`bg-white dark:bg-slate-800/80 dark:backdrop-blur-xl p-6 rounded-lg shadow-sm border relative overflow-hidden transition-all duration-300 ${cardBorder}`}>
                                    <div className="flex justify-between items-start mb-4">
                                        <div>
                                            <span className="text-xs font-semibold px-2 py-1 bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 rounded-md">
                                                {config.category}
                                            </span>
                                            <h3 className="mt-2 text-lg font-bold text-gray-900 dark:text-white">{config.name}</h3>
                                        </div>

                                        {/* Status Badge */}
                                        {data.status ? (
                                            <span className={`px-2 py-1 text-xs font-bold rounded-full flex items-center gap-1 ${badgeStyle}`}>
                                                <span className={`w-2 h-2 rounded-full ${dotColor} ${isOnline ? 'animate-ping' : ''}`}></span>
                                                {badgeLabel}
                                            </span>
                                        ) : (
                                            <span className="px-2 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-500">
                                                Loading...
                                            </span>
                                        )}
                                    </div>

                                    <div className="mt-2 mb-4">
                                        <Sparkline data={history} />
                                    </div>

                                    <div className="space-y-2 mt-4 font-mono text-sm relative z-10">
                                        <div className="flex justify-between text-gray-600 dark:text-gray-400">
                                            <span>HTTP Code:</span>
                                            <span className={data.statusCode ? codeColor : ''}>
                                                {data.statusCode || '-'}
                                            </span>
                                        </div>
                                        <div className="flex justify-between text-gray-600 dark:text-gray-400">
                                            <span>Latensi:</span>
                                            <span className="text-blue-600 dark:text-cyan-400">{data.latency !== undefined ? `${data.latency} ms` : '-'}</span>
                                        </div>
                                    </div>

                                    {data.errorMessage && (
                                        <div className={msgBoxStyle} title={data.errorMessage}>
                                            {data.errorMessage}
                                        </div>
                                    )}

                                    <div className="mt-6 flex justify-end">
                                        <button
                                            onClick={() => pingSingle(id)}
                                            className="text-sm px-3 py-1 bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-300 rounded transition-colors"
                                        >
                                            Ping Ulang
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
