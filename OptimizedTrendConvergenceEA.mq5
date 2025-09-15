//+------------------------------------------------------------------+
//|                                 OptimizedTrendConvergenceEA.mq5 |
//|                                     Developed by Jules (AI)     |
//|                                      https://www.example.com    |
//+------------------------------------------------------------------+
#property copyright "Copyright 2023, Your Name & Co."
#property link      "https://www.example.com"
#property description "A highly configurable EA implementing a trend convergence strategy with multiple filters."
#property version   "2.04" // Final Bugfix Revision

//--- Include the Standard Library for Trade Functions
#include <Trade\Trade.mqh>

//+------------------------------------------------------------------+
//| EA Input Parameters                                              |
//+------------------------------------------------------------------+
//--- Money Management
input group           "Money Management"
input double          Risk_Percentage = 1.0;                       // Risk per trade as % of account equity.
input bool            Use_ATR_SLTP = true;                         // Use ATR for Stop Loss and Take Profit?
input int             ATR_Period = 14;                             // Period for ATR calculation.
input double          SL_ATR_Mult = 1.5;                           // Multiplier for ATR-based Stop Loss.
input double          TP_ATR_Mult = 2.0;                           // Multiplier for ATR-based Take Profit.
input double          Take_Profit_Ratio = 1.5;                     // R:R Ratio for Fixed Pips SL/TP mode.
input int             Fixed_Stop_Loss_Pips = 50;                   // Fallback SL in pips if ATR is not used.

//--- Signals/Filters
input group           "Signals & Filters"
//--- EMA Settings
input int             FastEMA_Period = 20;                         // Fast EMA Period.
input int             SlowEMA_Period = 50;                         // Slow EMA Period.
input bool            Require_EMA_Cross = false;                   // Require a fresh EMA cross on the signal bar?
input double          Max_EMA_Gap_Pips = 15.0;                     // Max allowed gap between EMAs in pips.
input double          Max_Pullback_Distance_Pips = 10.0;           // Max distance price can be from Slow EMA.
//--- RSI Settings
input int             RSI_Period = 14;                             // RSI Period.
input double          RSI_Oversold = 30.0;                         // RSI Oversold Level.
input double          RSI_Overbought = 70.0;                       // RSI Overbought Level.
input int             RSI_Confirm_Bars = 1;                        // How many bars RSI must stay compliant.
//--- Volatility Filter
input double          Min_ATR_Pips = 5.0;                          // Minimum ATR value in pips to allow trading.
//--- Higher-Timeframe (HTF) Filter
input bool            Use_HTF_Confirm = false;                     // Enable Higher-Timeframe confirmation?
input ENUM_TIMEFRAMES HTF = PERIOD_H4;                             // Timeframe for HTF confirmation.
input int             HTF_SlowEMA_Period = 200;                    // Period for the HTF EMA.
input bool            Require_Strictly_Above_HTF_EMA = true;       // If true, price must be strictly above/below HTF EMA.

//--- Execution
input group           "Execution"
input ulong           Magic_Number = 123456;                       // EA's unique identifier for trades.
input int             SL_Buffer_Pips = 1;                          // Buffer in pips to add to Stop Loss.
input double          Max_Allowed_Spread_Pips = 2.5;               // Maximum allowed spread in pips for entry.
input bool            Allow_MultiPositions = false;                // Allow multiple positions per symbol?
input int             Max_Positions = 5;                           // Max number of concurrent positions if allowed.

//--- Position Management
input group           "Position Management"
input int             Breakeven_Trigger_Pips = 20;                 // Pips in profit to trigger breakeven.
input int             Breakeven_Offset_Pips = 2;                   // Pips to set SL ahead of entry price at breakeven.
input int             Trailing_Stop_Pips = 15;                     // Pips to trail the stop loss.

//--- Session Filter
input group           "Session Filter"
input bool            Limit_Sessions = false;                      // Enable session/time filter?
input string          Session1_Start_Time = "08:00";               // Session 1 Start (Broker Time HH:MM).
input string          Session1_End_Time = "16:00";                 // Session 1 End (Broker Time HH:MM).

//--- Notifications
input group           "Notifications"
input bool            Send_Email_Alerts = true;                    // Enable Email Alerts?
input bool            Show_Popup_Alerts = true;                    // Enable On-screen and Sound Alerts?


//--- Global Variables ---
int    ema_fast_handle;
int    ema_slow_handle;
int    rsi_handle;
int    atr_handle;
int    htf_ema_handle;

// Forward declaration for CTradeExt
class CTradeExt;
CTradeExt trade;

//+------------------------------------------------------------------+
//| Expert Initialization Function                                   |
//+------------------------------------------------------------------+
int OnInit()
{
   trade.SetExpertMagicNumber(Magic_Number);
   trade.SetMarginMode();
   PrintFormat("Initializing EA '%s' on %s, %s...", MQL5InfoString(MQL5_PROGRAM_NAME), _Symbol, EnumToString(_Period));

   ema_fast_handle = iMA(_Symbol, _Period, FastEMA_Period, 0, MODE_EMA, PRICE_CLOSE);
   ema_slow_handle = iMA(_Symbol, _Period, SlowEMA_Period, 0, MODE_EMA, PRICE_CLOSE);
   rsi_handle = iRSI(_Symbol, _Period, RSI_Period, PRICE_CLOSE);
   if(ema_fast_handle == INVALID_HANDLE || ema_slow_handle == INVALID_HANDLE || rsi_handle == INVALID_HANDLE)
   {
      Print("Error creating main indicator handles.");
      return(INIT_FAILED);
   }

   if(Use_ATR_SLTP || Min_ATR_Pips > 0)
   {
      atr_handle = iATR(_Symbol, _Period, ATR_Period);
      if(atr_handle == INVALID_HANDLE) { Print("Error creating ATR indicator handle."); return(INIT_FAILED); }
   }

   if(Use_HTF_Confirm)
   {
      htf_ema_handle = iMA(_Symbol, HTF, HTF_SlowEMA_Period, 0, MODE_EMA, PRICE_CLOSE);
      if(htf_ema_handle == INVALID_HANDLE) { Print("Error creating HTF EMA indicator handle."); return(INIT_FAILED); }
   }

   Print("EA Initialized Successfully.");
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
//| Expert Deinitialization Function                                 |
//+------------------------------------------------------------------+
void OnDeinit(const int reason)
{
   IndicatorRelease(ema_fast_handle);
   IndicatorRelease(ema_slow_handle);
   IndicatorRelease(rsi_handle);
   IndicatorRelease(atr_handle);
   IndicatorRelease(htf_ema_handle);
   Print("EA Deinitialized. Resources released.");
}

//+------------------------------------------------------------------+
//| Count open positions for the current symbol/magic                |
//+------------------------------------------------------------------+
int CountOpenPositions()
{
   int count = 0;
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      if(!PositionSelectByIndex(i)) continue;
      if(PositionGetString(POSITION_SYMBOL) == _Symbol && PositionGetInteger(POSITION_MAGIC) == (long)Magic_Number)
      {
         count++;
      }
   }
   return count;
}

//+------------------------------------------------------------------+
//| Expert Tick Function                                             |
//+------------------------------------------------------------------+
void OnTick()
{
   ManagePositions();

   static datetime last_bar_time = 0;
   datetime current_bar_time = (datetime)SeriesInfoInteger(_Symbol, _Period, SERIES_LAST_BAR_TIME);

   if(current_bar_time > last_bar_time)
   {
      last_bar_time = current_bar_time;

      if((bool)TerminalInfoInteger(TERMINAL_TRADE_ALLOWED))
      {
         int open_positions = CountOpenPositions();
         bool can_open_new_trade = Allow_MultiPositions ? (open_positions < Max_Positions) : (open_positions == 0);

         if(can_open_new_trade)
         {
            CheckForSignal();
         }
      }
   }
}

//+------------------------------------------------------------------+
//| Check For Signal                                                 |
//+------------------------------------------------------------------+
void CheckForSignal()
{
    if(!IsTradingSessionActive()) return;

    int data_to_copy = RSI_Confirm_Bars + 5; // Need enough data for all checks
    double ema_fast[], ema_slow[], rsi[], atr[];
    MqlRates prices[];

    ArrayResize(ema_fast, data_to_copy);
    ArrayResize(ema_slow, data_to_copy);
    ArrayResize(rsi, data_to_copy);
    ArrayResize(atr, data_to_copy);
    ArrayResize(prices, data_to_copy);

    if (CopyBuffer(ema_fast_handle, 0, 0, data_to_copy, ema_fast) < data_to_copy ||
        CopyBuffer(ema_slow_handle, 0, 0, data_to_copy, ema_slow) < data_to_copy ||
        CopyBuffer(rsi_handle, 0, 0, data_to_copy, rsi) < data_to_copy ||
        CopyRates(_Symbol, _Period, 0, data_to_copy, prices) < data_to_copy)
    {
        Print("Could not get enough history for signal checks.");
        return;
    }

    if(Use_ATR_SLTP || Min_ATR_Pips > 0)
    {
        if(CopyBuffer(atr_handle, 0, 0, data_to_copy, atr) < data_to_copy)
        {
            Print("Could not get ATR history for signal checks.");
            return;
        }
    }

    // --- CRITICAL: Reverse all arrays to work like a standard timeseries ---
    ArraySetAsSeries(prices, true);
    ArraySetAsSeries(ema_fast, true);
    ArraySetAsSeries(ema_slow, true);
    ArraySetAsSeries(rsi, true);
    ArraySetAsSeries(atr, true);
    // Now, index [0] is the current forming bar, [1] is the last closed bar (signal bar), etc.

    if (Min_ATR_Pips > 0 && (atr[1] / GetPipSize()) < Min_ATR_Pips) return;

    if (IsSignalValid(true, prices, ema_fast, ema_slow, rsi))
    {
        ExecuteTrade(true, prices[1], atr[1]);
        return;
    }

    if (IsSignalValid(false, prices, ema_fast, ema_slow, rsi))
    {
        ExecuteTrade(false, prices[1], atr[1]);
        return;
    }
}

//+------------------------------------------------------------------+
//| Signal Validation Logic                                          |
//+------------------------------------------------------------------+
bool IsSignalValid(bool is_buy, const MqlRates &prices[], const double &ema_fast[], const double &ema_slow[], const double &rsi[])
{
    // Arrays are now timeseries. Index 1 is the signal candle, 2 is the one before.
    double pip_size = GetPipSize();

    if(Use_HTF_Confirm && !CheckHTF(is_buy)) return false;

    if (!(is_buy ? ema_slow[1] > ema_slow[2] : ema_slow[1] < ema_slow[2])) return false;

    double ema_gap_now = MathAbs(ema_fast[1] - ema_slow[1]);
    double ema_gap_prev = MathAbs(ema_fast[2] - ema_slow[2]);
    if (ema_gap_now >= ema_gap_prev || (ema_gap_now / _Point) > Max_EMA_Gap_Pips) return false;

    if ((MathAbs(prices[1].close - ema_slow[1]) / pip_size) > Max_Pullback_Distance_Pips) return false;

    if (Require_EMA_Cross)
    {
        bool cross_passed = is_buy ? (ema_fast[2] <= ema_slow[2] && ema_fast[1] > ema_slow[1]) :
                                     (ema_fast[2] >= ema_slow[2] && ema_fast[1] < ema_slow[1]);
        if (!cross_passed) return false;
    }

    if(RSI_Confirm_Bars < 1) return false;
    if (is_buy)
    {
        for (int i = 1; i <= RSI_Confirm_Bars; i++)
        {
            if (rsi[i] < RSI_Oversold) return false;
        }
        if (rsi[RSI_Confirm_Bars + 1] >= RSI_Oversold) return false;
    }
    else
    {
        for (int i = 1; i <= RSI_Confirm_Bars; i++)
        {
            if (rsi[i] > RSI_Overbought) return false;
        }
        if (rsi[RSI_Confirm_Bars + 1] <= RSI_Overbought) return false;
    }

    return true;
}

//+------------------------------------------------------------------+
//| Higher-Timeframe (HTF) Filter                                    |
//+------------------------------------------------------------------+
bool CheckHTF(bool is_buy)
{
    double htf_ema_buffer[1];
    if(CopyBuffer(htf_ema_handle, 0, 1, 1, htf_ema_buffer) < 1) return false;

    MqlTick tick;
    if(!SymbolInfoTick(_Symbol, tick)) return false;
    double current_price = tick.last;

    if(is_buy)
    {
        return Require_Strictly_Above_HTF_EMA ? (current_price > htf_ema_buffer[0]) : (current_price >= htf_ema_buffer[0]);
    }
    else
    {
        return Require_Strictly_Above_HTF_EMA ? (current_price < htf_ema_buffer[0]) : (current_price <= htf_ema_buffer[0]);
    }
}

//+------------------------------------------------------------------+
//| Session/Time Filter                                              |
//+------------------------------------------------------------------+
bool IsTradingSessionActive()
{
    if(!Limit_Sessions) return true;

    MqlDateTime current_time;
    TimeCurrent(current_time);

    int start_hour = int(StringSubstr(Session1_Start_Time, 0, 2));
    int start_min = int(StringSubstr(Session1_Start_Time, 3, 2));
    int end_hour = int(StringSubstr(Session1_End_Time, 0, 2));
    int end_min = int(StringSubstr(Session1_End_Time, 3, 2));

    long time_start = start_hour * 3600 + start_min * 60;
    long time_end = end_hour * 3600 + end_min * 60;
    long time_current = current_time.hour * 3600 + current_time.min * 60 + current_time.sec;

    if (time_start < time_end)
        return (time_current >= time_start && time_current <= time_end);
    else
        return (time_current >= time_start || time_current <= time_end);
}

//+------------------------------------------------------------------+
//| Execute Trade (Unified Function)                                 |
//+------------------------------------------------------------------+
void ExecuteTrade(bool is_buy, const MqlRates &signal_candle, double atr_value)
{
    double pip_size = GetPipSize();

    double spread = SymbolInfoDouble(_Symbol, SYMBOL_ASK) - SymbolInfoDouble(_Symbol, SYMBOL_BID);
    if (Max_Allowed_Spread_Pips > 0 && (spread / pip_size) > Max_Allowed_Spread_Pips)
    {
        PrintFormat("Trade aborted. Spread (%.1f pips) > Max allowed (%.1f pips).", spread / pip_size, Max_Allowed_Spread_Pips);
        return;
    }

    double sl_price, tp_price;
    ENUM_ORDER_TYPE order_type = is_buy ? ORDER_TYPE_BUY : ORDER_TYPE_SELL;
    double entry_price = is_buy ? SymbolInfoDouble(_Symbol, SYMBOL_ASK) : SymbolInfoDouble(_Symbol, SYMBOL_BID);

    if(Use_ATR_SLTP)
    {
        sl_price = is_buy ? entry_price - (atr_value * SL_ATR_Mult) : entry_price + (atr_value * SL_ATR_Mult);
        tp_price = is_buy ? entry_price + (atr_value * TP_ATR_Mult) : entry_price - (atr_value * TP_ATR_Mult);
    }
    else
    {
        double sl_distance = Fixed_Stop_Loss_Pips * pip_size;
        sl_price = is_buy ? entry_price - sl_distance : entry_price + sl_distance;
        tp_price = is_buy ? entry_price + (sl_distance * Take_Profit_Ratio) : entry_price - (sl_distance * Take_Profit_Ratio);
    }

    double sl_with_buffer = is_buy ? sl_price - (SL_Buffer_Pips * pip_size) : sl_price + (SL_Buffer_Pips * pip_size);
    int stops_level = (int)SymbolInfoInteger(_Symbol, SYMBOL_TRADE_STOPS_LEVEL);
    double min_stop_dist = stops_level * _Point;

    if(is_buy && entry_price - sl_with_buffer < min_stop_dist) sl_with_buffer = entry_price - min_stop_dist;
    if(!is_buy && sl_with_buffer - entry_price < min_stop_dist) sl_with_buffer = entry_price + min_stop_dist;

    double lot_size = CalculateLotSize(order_type, sl_with_buffer);
    if(lot_size <= 0) return;

    string comment = is_buy ? "Buy by TrendConvergenceEA" : "Sell by TrendConvergenceEA";
    if(trade.PlaceOrder(order_type, _Symbol, lot_size, sl_with_buffer, tp_price, comment))
    {
        PrintFormat("Order placed successfully. Ticket #%d", (int)trade.ResultOrder());
    }
    else
    {
        PrintFormat("Order failed. Error: %d - %s", trade.ResultRetcode(), trade.ResultComment());
    }
}

//+------------------------------------------------------------------+
//| Manage Positions                                                 |
//+------------------------------------------------------------------+
void ManagePositions()
{
   double pip_size = GetPipSize();
   MqlTick tick;

   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      if(!PositionSelectByIndex(i)) continue;
      if(PositionGetString(POSITION_SYMBOL) != _Symbol || PositionGetInteger(POSITION_MAGIC) != (long)Magic_Number) continue;
      if(!SymbolInfoTick(_Symbol, tick)) continue;

      ulong  ticket       = PositionGetInteger(POSITION_TICKET);
      long   type         = PositionGetInteger(POSITION_TYPE);
      double open_price   = PositionGetDouble(POSITION_PRICE_OPEN);
      double current_sl   = PositionGetDouble(POSITION_SL);
      double current_tp   = PositionGetDouble(POSITION_TP);

      if(type == POSITION_TYPE_BUY)
      {
         double current_price = tick.bid;
         double profit_pips = (current_price - open_price) / pip_size;

         if(profit_pips >= Breakeven_Trigger_Pips)
         {
            double target_be_sl = open_price + (Breakeven_Offset_Pips * pip_size);
            if(current_sl < target_be_sl)
            {
               if(trade.PositionModify(ticket, target_be_sl, current_tp))
                  PrintFormat("Position #%d: Moved SL to Breakeven+ at %.5f", (int)ticket, target_be_sl);
               continue;
            }
         }

         if(current_sl >= open_price)
         {
            double new_sl = current_price - (Trailing_Stop_Pips * pip_size);
            if(new_sl > current_sl && new_sl < current_price)
            {
               if(trade.PositionModify(ticket, new_sl, current_tp))
                  PrintFormat("Position #%d: Trailed SL to %.5f", (int)ticket, new_sl);
            }
         }
      }
      else if(type == POSITION_TYPE_SELL)
      {
         double current_price = tick.ask;
         double profit_pips = (open_price - current_price) / pip_size;

         if(profit_pips >= Breakeven_Trigger_Pips)
         {
            double target_be_sl = open_price - (Breakeven_Offset_Pips * pip_size);
            if(current_sl > target_be_sl || current_sl == 0)
            {
               if(trade.PositionModify(ticket, target_be_sl, current_tp))
                  PrintFormat("Position #%d: Moved SL to Breakeven+ at %.5f", (int)ticket, target_be_sl);
               continue;
            }
         }

         if(current_sl <= open_price && current_sl != 0)
         {
            double new_sl = current_price + (Trailing_Stop_Pips * pip_size);
            if(new_sl < current_sl && new_sl > current_price)
            {
               if(trade.PositionModify(ticket, new_sl, current_tp))
                  PrintFormat("Position #%d: Trailed SL to %.5f", (int)ticket, new_sl);
            }
         }
      }
   }
}

//+------------------------------------------------------------------+
//| Calculate Lot Size                                               |
//+------------------------------------------------------------------+
double CalculateLotSize(ENUM_ORDER_TYPE order_type, double sl_price)
{
    double account_equity = AccountInfoDouble(ACCOUNT_EQUITY);
    if(account_equity <= 0)
    {
        PrintFormat("Invalid account equity: %.2f", account_equity);
        return 0.0;
    }
    double risk_amount = account_equity * (Risk_Percentage / 100.0);
    double entry_price = (order_type == ORDER_TYPE_BUY) ? SymbolInfoDouble(_Symbol, SYMBOL_ASK) : SymbolInfoDouble(_Symbol, SYMBOL_BID);

    double loss_for_one_lot = 0;
    if(!OrderCalcProfit(order_type, _Symbol, 1.0, entry_price, sl_price, loss_for_one_lot))
    {
        PrintFormat("Error calculating profit/loss for lot size: #%d", GetLastError());
        return 0.0;
    }

    if(MathAbs(loss_for_one_lot) <= 1e-10)
    {
        Print("Cannot calculate lot size. Potential loss for 1 lot is zero or invalid.");
        return 0.0;
    }

    double lot_size = risk_amount / MathAbs(loss_for_one_lot);
    double vol_step = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_STEP);
    lot_size = floor(lot_size / vol_step) * vol_step;

    double min_vol = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_MIN);
    double max_vol = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_MAX);
    if(lot_size < min_vol)
        lot_size = min_vol;
    if(lot_size > max_vol)
        lot_size = max_vol;

    if (lot_size * MathAbs(loss_for_one_lot) > risk_amount && lot_size == min_vol)
    {
       PrintFormat("Cannot afford minimum lot size (%.2f) with current risk percentage (%.2f%%).", min_vol, Risk_Percentage);
       return 0.0;
    }

    return lot_size;
}

//+------------------------------------------------------------------+
//| Get Pip Size                                                     |
//+------------------------------------------------------------------+
double GetPipSize()
{
    int digits = (int)SymbolInfoInteger(_Symbol, SYMBOL_DIGITS);
    if (digits == 3 || digits == 5 || digits == 1)
        return _Point * 10;
    return _Point;
}

//+------------------------------------------------------------------+
//| CTrade extension for cleaner order sending                       |
//+------------------------------------------------------------------+
class CTradeExt : public CTrade
{
public:
    bool PlaceOrder(ENUM_ORDER_TYPE type, string symbol, double volume, double sl, double tp, string comment)
    {
        if(type == ORDER_TYPE_BUY)
            return Buy(volume, symbol, 0.0, sl, tp, comment);
        else
            return Sell(volume, symbol, 0.0, sl, tp, comment);
    }
};
CTradeExt trade;
//+------------------------------------------------------------------+
