
import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { ArrowLeft, Send, MessageSquare, History } from 'lucide-react';

const Support: React.FC = () => {
  const { currentUser, setTickets, tickets } = useApp();
  const navigate = useNavigate();
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    // @ts-ignore
    if (window.ClassicEditor) {
      // @ts-ignore
      window.ClassicEditor.create(document.querySelector('#ticket-editor'))
        .then(editor => {
          editor.model.document.on('change:data', () => {
            setMessage(editor.getData());
          });
        })
        .catch(error => {
          console.error(error);
        });
    }
  }, []);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!currentUser || !message) return;
    
    setLoading(true);
    setTimeout(() => {
      const newTicket = {
        id: Math.random().toString(36).substr(2, 9).toUpperCase(),
        userId: currentUser.id,
        subject,
        message,
        status: 'open' as const,
        createdAt: new Date().toISOString(),
        replies: []
      };
      setTickets(prev => [newTicket, ...prev]);
      setLoading(false);
      setSubject('');
      setMessage('');
      alert('Ticket submitted successfully!');
      navigate('/dashboard');
    }, 1500);
  };

  const myTickets = tickets.filter(t => t.userId === currentUser?.id);

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <ArrowLeft className="text-gray-600 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-bold">Support Center</h1>
      </div>

      <div className="p-4 space-y-6 flex-1">
        <div className="bg-white p-5 rounded-2xl shadow-sm space-y-4">
          <div className="flex items-center gap-3 mb-2">
            <MessageSquare className="text-billpay-green" />
            <h3 className="font-bold text-gray-800">Open a New Ticket</h3>
          </div>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-xs font-bold text-gray-400 uppercase block mb-1">Subject</label>
              <input 
                type="text" 
                required
                className="w-full p-4 bg-gray-50 text-gray-900 rounded-xl outline-none focus:ring-2 focus:ring-billpay-green/20"
                placeholder="e.g. Payment Issue"
                value={subject}
                onChange={(e) => setSubject(e.target.value)}
              />
            </div>
            <div>
              <label className="text-xs font-bold text-gray-400 uppercase block mb-1">Detailed Message</label>
              <div className="rounded-xl overflow-hidden border border-gray-100 min-h-[150px]">
                <div id="ticket-editor"></div>
              </div>
            </div>
            <button 
              type="submit"
              disabled={loading}
              className="w-full bg-billpay-green text-white py-4 rounded-xl font-bold shadow-lg flex items-center justify-center gap-2 active:scale-95 transition-all"
            >
              {loading ? 'Sending...' : <><Send size={18} /> Send Ticket</>}
            </button>
          </form>
        </div>

        <div className="space-y-4 pb-10">
          <h3 className="text-sm font-bold text-gray-800 flex items-center gap-2 px-2">
            <History size={18} className="text-gray-400" /> My Tickets
          </h3>
          {myTickets.length === 0 ? (
            <div className="text-center py-10 text-gray-400 text-sm">No support history found.</div>
          ) : (
            <div className="space-y-3">
              {myTickets.map(ticket => (
                <div key={ticket.id} className="bg-white p-4 rounded-2xl border border-gray-100">
                  <div className="flex justify-between items-start mb-2">
                    <span className="text-xs font-bold text-gray-800">{ticket.subject}</span>
                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${ticket.status === 'open' ? 'bg-amber-100 text-amber-600' : 'bg-green-100 text-green-600'}`}>
                      {ticket.status.toUpperCase()}
                    </span>
                  </div>
                  <div className="text-[11px] text-gray-500 line-clamp-2 mb-2" dangerouslySetInnerHTML={{ __html: ticket.message }} />
                  <div className="text-[9px] text-gray-400 font-bold uppercase">{new Date(ticket.createdAt).toLocaleDateString()}</div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default Support;
